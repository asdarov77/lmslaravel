// Все ответы группы api заворачиваются middleware ApiResponseEnvelope
// в конверт { success, data, error, meta }.
// В компонентах и сторе в state/business-logic должен попадать payload,
// то есть response.data.data, а не весь конверт.

/**
 * Достаёт полезную нагрузку из ответа.
 * Поддерживает как конверт, так и «голый» ответ без конверта.
 */
export const unwrapResponse = (response) => {
  if (response === null || response === undefined) return null

  const body = typeof response === 'object' && 'data' in response ? response.data : response

  if (body === null || body === undefined) return null

  if (typeof body === 'object' && 'success' in body && 'data' in body) {
    return body.data
  }

  return body
}

/**
 * Всегда возвращает массив — защищает .map/.sort/.filter в шаблонах
 * от неожиданного объекта-конверта или null.
 */
export const asArray = (value) => (Array.isArray(value) ? value : [])

/**
 * Достаёт payload и гарантирует, что это массив.
 */
export const unwrapArray = (response) => asArray(unwrapResponse(response))

/**
 * Достаёт поле из payload, не падая на отсутствующих полях.
 */
export const unwrapField = (response, field) => {
  const payload = unwrapResponse(response)
  return payload && typeof payload === 'object' ? payload[field] : undefined
}
