import $api from './httpClient'

/**
 * Клиент player-endpoint (см. App\Http\Controllers\CoursePlayerController).
 *
 * Контракт ответа зафиксирован тестом tests/Feature/CoursePlayerEndpointTest.php.
 * Все пути относительные — baseURL и Bearer-токен добавляет httpClient.
 */

/**
 * Все ответы /api* проходят через middleware ApiResponseEnvelope и приходят
 * в конверте {success, data, error, meta}. axios отдаёт весь конверт в
 * response.data, поэтому полезная нагрузка лежит в response.data.data.
 * Без разворачивания плеер читал data.course === undefined — «пустой кадр».
 */
const unwrap = (response) => response?.data?.data ?? null

export async function fetchPlayerPayload(courseId) {
  return unwrap(await $api.get(`/api/course/${courseId}/player`))
}

export async function saveProgress(courseId, { lastItemId, lastAnchor = null, percent = 0 }) {
  return unwrap(await $api.post(`/api/course/${courseId}/progress`, {
    last_item_id: lastItemId,
    last_anchor: lastAnchor,
    percent,
  }))
}

export async function sendViewEvent(courseId, { aukstructureId, secondsViewed, scrollPercent }) {
  return unwrap(await $api.post(`/api/course/${courseId}/view`, {
    aukstructure_id: aukstructureId,
    seconds_viewed: secondsViewed,
    scroll_percent: scrollPercent,
  }))
}

export async function searchFiles({ query, path, aircraft }) {
  return unwrap(await $api.post('/api/search-files/', { query, path, aircraft }))
}

/**
 * URL материала для iframe.
 *
 * P0-3 (аудит): авторизация кадра переехала с Sanctum-токена в query (?t=...)
 * на короткоживущую HMAC-подпись пары (course|item|slot) — см. бэкенд
 * App\Support\CourseFrameSigner и middleware 'frame.signature'.
 *
 * Почему токен в URL был критической уязвимостью: долгий Sanctum-токен
 * (expires_at = null) оседал в логах nginx/прокси, истории браузера и
 * Referer вложенных ресурсов; утечка такого URL открывала ВЕСЬ аккаунт.
 * Подпись привязана к одному уроку и живёт ровно один слот (10 минут) —
 * её перехват даёт доступ лишь к этому материалу на короткое окно.
 *
 * Бонус производительности: подпись стабильна внутри слота, поэтому все
 * студенты получают ОДИН И ТОТ ЖЕ URL урока и браузер переиспользует
 * private-кэш (Cache-Control: private, max-age=TTL).
 */
export function contentFrameUrl(courseId, itemId, { highlight = '' } = {}) {
  const base = import.meta.env.VITE_APP_URL || ''
  const params = new URLSearchParams()
  const token = localStorage.getItem('token')
  if (token) {
    const dot = token.indexOf('.')
    if (dot > 0) {
      params.set('exp', String(dot))
      params.set('sig', token.slice(dot + 1))
    }
  }
  if (highlight && highlight.trim().length >= 3) params.set('highlight', highlight.trim())
  const qs = params.toString()
  return `${base}/api/course/${courseId}/content/${itemId}${qs ? '?' + qs : ''}`
}
