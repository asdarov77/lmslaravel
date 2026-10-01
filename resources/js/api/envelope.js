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

/**
 * Достаёт поле из meta конверта.
 *
 * Зачем: часть сводок (например, результат импорта класса) лежит не в data,
 * а в meta — там, где по контракту и должна быть служебная информация.
 */
export const metaField = (response, field) => {
  if (response === null || response === undefined) return undefined
  const body = response.data
  if (body === null || body === undefined || typeof body !== 'object') return undefined
  const meta = body.meta
  if (meta === null || meta === undefined || typeof meta !== 'object') return undefined
  return meta[field]
}

/**
 * Собирает query-параметры, отбрасывая нечисловые значения.
 *
 * Зачем: страницы получали id из query-параметров, и parseInt(undefined)
 * давал NaN. Конкатенация превращала это в "?course_id=NaN", на что
 * бэкенд отвечал 422 (валидация 'int'), и страница сыпала ошибкой в
 * консоль. Здесь значения, которые не являются конечными числами,
 * просто не попадают в запрос — фильтр считается незаданным.
 *
 * @param {Record<string, unknown>} params
 * @returns {Record<string, number|string|boolean>}
 */
export const numericQuery = params => {
  const out = {};
  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;
    const num = Number(value);
    if (Number.isFinite(num)) {
      out[key] = num;
      return;
    }
    // Нечисловое не-пустое значение сохраняем как есть (например булевы флаги),
    // чтобы не потерять осмысленные параметры.
    if (typeof value === 'string') out[key] = value;
  });
  return out;
};
