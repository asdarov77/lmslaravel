import httpClient from './httpClient'
import { TokenService } from '../services/storage.service'

/**
 * Тренажёр: локальная генерация вопросов по материалам курсов.
 *
 * Все ответы приходят в конверте {success,data,error,meta}, поэтому
 * компоненты разбирают их через unwrapResponse (см. api/envelope).
 *
 * Отдельно от course.api: тренажёр не имеет отношения к экзаменам и не
 * должен попадать в модуль курсов — иначе «историю обучения» придётся
 * отделять от «истории экзаменов», а это разные вещи по определению.
 */

export const fetchTutorHealth = () => httpClient.get('/api/v1/tutor/health')

export const fetchTutorMaterials = () => httpClient.get('/api/v1/tutor/materials')

export const startTutorSession = (materialId, count) =>
  httpClient.post('/api/v1/tutor/sessions', { material_id: materialId, count })

export const fetchTutorSession = (sessionId) => httpClient.get(`/api/v1/tutor/sessions/${sessionId}`)

export const fetchNextQuestion = (sessionId) =>
  httpClient.get(`/api/v1/tutor/sessions/${sessionId}/next-question`)

export const answerQuestion = (itemId, payload) =>
  httpClient.post(`/api/v1/tutor/items/${itemId}/answer`, payload)

export const finishTutorSession = (sessionId) =>
  httpClient.post(`/api/v1/tutor/sessions/${sessionId}/finish`)

export const fetchTutorStats = () => httpClient.get('/api/v1/tutor/stats')

// Методистские методы.
export const fetchAdminMaterials = () => httpClient.get('/api/v1/tutor/admin/materials')

export const indexCourseMaterial = (courseId) =>
  httpClient.post('/api/v1/tutor/admin/materials/index', { course_id: courseId })

/**
 * Чат-подсказчик в плеере (TutorDock, blueprint §3.3): SSE поверх POST.
 *
 * Здесь осознанно НЕ используется httpClient/axios: axios буферизирует
 * тело целиком и не даёт читать поток по кадрам. Формат сервера
 * (TutorController::chat): `event: meta` → `data: {"t":"…"}` токенами →
 * `event: done`; ошибки движка ДО начала стрима приходят обычным
 * envelope-JSON (503 + engineState), ошибки ПОСЛЕ начала — `event: error`.
 *
 * AbortController обязателен: unmount урока должен обрывать генерацию,
 * иначе док продолжает жечь CPU модели на уже закрытой странице.
 *
 * @param {{course_id:number, item_id?:number|null, question:string,
 *          hint_level?:string, context_off?:boolean}} payload
 * @param {{signal?:AbortSignal, onMeta?:(m:object)=>void,
 *          onToken?:(t:string)=>void, onDone?:(d:object)=>void,
 *          onError?:(message:string, info:{status?:number,engineState?:string|null})=>void}} handlers
 * @returns {Promise<void>} разрешается после конца потока; отклоняется
 *   только на сетевом сбое (AbortError — отмена, считается успехом).
 */
export const streamTutorChat = async (payload, handlers = {}) => {
  const { signal, onMeta, onToken, onDone, onError } = handlers

  // Токен — тот же Bearer, что ставит интерцептор httpClient: нативный
  // fetch его не наследует, заголовок собираем вручную.
  const token = TokenService.getToken()

  let response
  try {
    response = await fetch('/api/v1/tutor/chat', {
      method: 'POST',
      signal,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: JSON.stringify(payload),
    })
  } catch (e) {
    // Отмена при unmount — штатное завершение, не ошибка.
    if (e?.name === 'AbortError') return
    throw e
  }

  if (!response.ok || !response.body) {
    // До начала стрима ошибки приходят envelope-ом (503 движок
    // недоступен, 429 лимит, 403 права). Рисуем их через onError, а не
    // кидаем исключение: док остаётся рабочим (cooldown/countdown).
    let message = `HTTP ${response.status}`
    let engineState = null
    let retryAfter = null
    try {
      const body = await response.json()
      message = body?.error || message
      engineState = body?.meta?.engineState ?? null
      // throttle:chat кладёт секунды до сброса в meta.retryAfter (envelope) —
      // без них кулдаун дока стартовал бы с заглушки 60 c вместо реальных.
      const ra = Number(body?.meta?.retryAfter)
      if (Number.isFinite(ra) && ra > 0) retryAfter = Math.ceil(ra)
    } catch (_) { /* не-JSON ответ: оставляем код статуса */ }
    if (retryAfter === null) {
      const headerRa = Number(response.headers?.get?.('Retry-After'))
      if (Number.isFinite(headerRa) && headerRa > 0) retryAfter = Math.ceil(headerRa)
    }

    if (onError) onError(message, { status: response.status, engineState, retryAfter })
    else throw new Error(message)
    return
  }

  const reader = response.body.getReader()
  // fatal: true — движок декодера обязан бросать исключение на битых
  // последовательностях вместо молча подменять их на U+FFFD (иначе в ответ
  // тьютора попадали бы «символы замены» вместо кириллицы).
  const decoder = new TextDecoder('utf-8', { fatal: true })
  let buffer = ''

  // Кадр SSE отделён пустой строкой; внутри — строки `event:`/`data:`.
  const dispatch = (rawFrame) => {
    const frame = rawFrame.replace(/\r\n/g, '\n').trim()
    if (frame === '') return

    let event = 'message'
    const dataLines = []

    for (const line of frame.split('\n')) {
      if (line.startsWith('event:')) event = line.slice(6).trim()
      else if (line.startsWith('data:')) dataLines.push(line.slice(5).trimStart())
    }

    if (dataLines.length === 0) return

    let parsed = null
    try {
      parsed = JSON.parse(dataLines.join('\n'))
    } catch (_) {
      return // повреждённый кадр игнорируем: оборванный JSON-токен
    }

    if (event === 'meta') onMeta?.(parsed)
    else if (event === 'done') onDone?.(parsed)
    else if (event === 'error') onError?.(String(parsed.message ?? ''), {})
    else if (parsed.t != null) onToken?.(String(parsed.t))
  }

  try {
    for (;;) {
      const { value, done } = await reader.read()
      if (done) break

      buffer += decoder.decode(value, { stream: true })

      let sep
      while ((sep = buffer.indexOf('\n\n')) !== -1) {
        dispatch(buffer.slice(0, sep))
        buffer = buffer.slice(sep + 2)
      }
    }

    buffer += decoder.decode() // хвост без завершающего перевода строк
    if (buffer.trim() !== '') dispatch(buffer)
  } catch (e) {
    if (e?.name === 'AbortError') {
      // Поток оборван намеренно (unmount) — дочитывать нельзя.
      try { await reader.cancel() } catch (_) { /* уже закрыт */ }
      return
    }
    throw e
  }
}

export default {
  fetchTutorHealth,
  fetchTutorMaterials,
  startTutorSession,
  fetchTutorSession,
  fetchNextQuestion,
  answerQuestion,
  finishTutorSession,
  fetchTutorStats,
  fetchAdminMaterials,
  indexCourseMaterial,
  streamTutorChat,
}
