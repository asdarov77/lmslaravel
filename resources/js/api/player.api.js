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

export function fetchLink(itemId) {
  return $api.get(`/api/getlink/${itemId}`)
}

export function searchFiles({ query, path, aircraft }) {
  return $api.post('/api/search-files/', { query, path, aircraft })
}
