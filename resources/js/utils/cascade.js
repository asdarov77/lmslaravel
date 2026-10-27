/**
 * Каскадные select (категория → тип → Aircraft).
 *
 * Опции шага могут быть массивом или функцией от уже выбранных
 * значений. При смене уровня все младшие сбрасываются — иначе
 * оставался бы выбран «тип» из старой категории, которого в новой нет,
 * и на бэкенд уходила несогласованная пара id.
 */

/** Опции шага с учётом текущих значений. */
export function cascadeOptions(step, values) {
  if (!step) return []
  const source = typeof step.options === 'function' ? step.options(values) : step.options

  return Array.isArray(source) ? source : []
}

/** Задать значение уровня i, сбросив все младшие. */
export function setCascadeValue(steps, values, index, value) {
  const next = Array.isArray(values) ? values.slice() : []
  next[index] = value
  for (let i = index + 1; i < steps.length; i += 1) next[i] = null

  return next
}

/** Какие шаги доступны: следующий — после заполнения предыдущего. */
export function activeCascadeSteps(steps, values) {
  return steps.map((step, index) => index === 0 || (values[index - 1] !== null && values[index - 1] !== undefined && values[index - 1] !== ''))
}

export default { cascadeOptions, setCascadeValue, activeCascadeSteps }
