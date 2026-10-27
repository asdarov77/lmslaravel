/**
 * Операции над массивами без мутации исходного.
 *
 * Нужны repeatable-полям (варианты ответов, расписание): add/remove/
 * move должны возвращать НОВЫЙ массив, чтобы v-model-связка и
 * computed/dirty в useForm замечали изменение.
 */

/** Переставить элемент from -> to. Возвращает новый массив. */
export function moveItem(list, from, to) {
  const array = Array.isArray(list) ? list : []
  if (from === to || from < 0 || to < 0 || from >= array.length || to >= array.length) {
    return array.slice()
  }

  const copy = array.slice()
  const [item] = copy.splice(from, 1)
  copy.splice(to, 0, item)

  return copy
}

/** Удалить элемент по индексу. */
export function removeAt(list, index) {
  const array = Array.isArray(list) ? list : []

  return array.filter((_, i) => i !== index)
}

/** Вставить элемент в позицию. */
export function insertAt(list, index, item) {
  const array = Array.isArray(list) ? list : []
  const copy = array.slice()
  copy.splice(Math.max(0, Math.min(index, copy.length)), 0, item)

  return copy
}

/** Глубокая копия элемента (чтобы дубликаты не делили ссылку). */
export function cloneItem(item) {
  try {
    return JSON.parse(JSON.stringify(item))
  } catch (error) {
    return item
  }
}

export default { moveItem, removeAt, insertAt, cloneItem }
