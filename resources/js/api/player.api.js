import $api from './httpClient'

/**
 * Клиент player-endpoint (см. App\Http\Controllers\CoursePlayerController).
 *
 * Контракт ответа зафиксирован тестом tests/Feature/CoursePlayerEndpointTest.php.
 * Все пути относительные — baseURL и Bearer-токен добавляет httpClient.
 */

export function fetchPlayerPayload(courseId) {
  return $api.get(`/api/course/${courseId}/player`)
}

export function saveProgress(courseId, { lastItemId, lastAnchor = null, percent = 0 }) {
  return $api.post(`/api/course/${courseId}/progress`, {
    last_item_id: lastItemId,
    last_anchor: lastAnchor,
    percent,
  })
}

export function sendViewEvent(courseId, { aukstructureId, secondsViewed, scrollPercent }) {
  return $api.post(`/api/course/${courseId}/view`, {
    aukstructure_id: aukstructureId,
    seconds_viewed: secondsViewed,
    scroll_percent: scrollPercent,
  })
}

export function searchFiles({ query, path, aircraft }) {
  return $api.post('/api/search-files/', { query, path, aircraft })
}

/**
 * URL материала для iframe. Авторизация — через ?t=<sanctum token>:
 * запрос кадра идёт без заголовков (iframe их не шлёт), токен в query
 * проверяет middleware 'auth' на маршрутах /course/{id}/content|raw.
 * Подпись/базовый путь больше не живут во фронтенд-кэше (Д1): ответ всегда no-store.
 */
export function contentFrameUrl(courseId, itemId, { highlight = '' } = {}) {
  const base = import.meta.env.VITE_APP_URL || ''
  const params = new URLSearchParams()
  const token = localStorage.getItem('token')
  if (token) params.set('t', token)
  if (highlight && highlight.trim().length >= 3) params.set('highlight', highlight.trim())
  const qs = params.toString()
  return `${base}/api/course/${courseId}/content/${itemId}${qs ? '?' + qs : ''}`
}
