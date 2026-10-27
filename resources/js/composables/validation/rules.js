/**
 * Правила валидации для useForm.
 *
 * Каждое правило — функция `(value, values) => true | string`:
 * `true` — значение корректно, строка — текст ошибки. Правило может
 * быть async (например, проверка уникальности на сервере) — useForm
 * умеет ждать результат.
 *
 * Почему свой набор, а не zod/vee-validate: формы проекта уже описаны
 * объектами-схемами, а десяток правил не стоит ещё одной зависимости
 * (тот же выбор, что и с графиками — пишем лёгкий SVG ChartsKit).
 *
 * Правило `required` помечается свойством `__required = true`, чтобы
 * FormField мог показать звёздочку у подписи, не дублируя список
 * обязательных полей в шаблоне.
 */

/** Пустое значение: null/undefined, пустая строка, пустой массив. */
export const isEmpty = (value) =>
  value === null ||
  value === undefined ||
  (typeof value === 'string' && value.trim() === '') ||
  (Array.isArray(value) && value.length === 0)

/** Помечает правило как обязательное (для звёздочки в FormField). */
const markRequired = (rule) => {
  rule.__required = true

  return rule
}

export const required = (message = 'Обязательное поле') =>
  markRequired((value) => (isEmpty(value) ? message : true))

export const minLength = (min, message = null) => (value) => {
  if (isEmpty(value)) return true

  return String(value).trim().length >= min ? true : message || `Минимум ${min} символов`
}

export const maxLength = (max, message = null) => (value) => {
  if (isEmpty(value)) return true

  return String(value).length <= max ? true : message || `Не более ${max} символов`
}

export const length = (min, max, message = null) => (value) => {
  if (isEmpty(value)) return true
  const size = String(value).length

  return size >= min && size <= max ? true : message || `От ${min} до ${max} символов`
}

export const pattern = (regexp, message = 'Некорректное значение') => (value) => {
  if (isEmpty(value)) return true

  return regexp.test(String(value)) ? true : message
}

// «Мягкая» проверка e-mail: строка без пробелов, одна @, точка в домене.
// Полную валидацию всё равно делает бэкенд — здесь цель не пропустить
// явную опечатку до запроса.
export const email = (message = 'Некорректный e-mail') =>
  pattern(/^[^\s@]+@[^\s@]+\.[^\s@]+$/, message)

export const numeric = (message = 'Допустимы только цифры') => (value) => {
  if (isEmpty(value)) return true

  return /^-?\d+(?:[.,]\d+)?$/.test(String(value).trim()) ? true : message
}

export const integer = (message = 'Введите целое число') => (value) => {
  if (isEmpty(value)) return true

  return /^-?\d+$/.test(String(value).trim()) ? true : message
}

/** Разумная проверка телефона: 10–15 цифр, допускаются +, скобки, дефисы. */
export const phone = (message = 'Некорректный номер телефона') => (value) => {
  if (isEmpty(value)) return true
  const digits = String(value).replace(/\D/g, '')

  return /^[+\d()\-\s]+$/.test(String(value)) && digits.length >= 10 && digits.length <= 15
    ? true
    : message
}

export const min = (limit, message = null) => (value) => {
  if (isEmpty(value)) return true
  const num = Number(value)

  return !Number.isNaN(num) && num >= limit ? true : message || `Не меньше ${limit}`
}

export const max = (limit, message = null) => (value) => {
  if (isEmpty(value)) return true
  const num = Number(value)

  return !Number.isNaN(num) && num <= limit ? true : message || `Не больше ${limit}`
}

export const oneOf = (allowed, message = 'Недопустимое значение') => (value) => {
  if (isEmpty(value)) return true

  // Сравниваем через строку: значения из select/radio приходят строками,
  // а список допустимых часто задан числами (1, 2, ...).
  const ok = allowed.some((item) => String(item) === String(value))

  return ok ? true : message
}

/**
 * Совпадение с другим полем (подтверждение пароля).
 * `other` — имя поля или геттер, возвращающий значение.
 */
export const sameAs = (other, message = 'Значения не совпадают') => (value, values) => {
  const reference = typeof other === 'function' ? other(values) : values?.[other]

  return value === reference ? true : message
}

/**
 * Уникальность по уже загруженному списку.
 *
 * `list` может быть массивом или геттером (getter нужен, когда список
 * подгружается асинхронно и на момент описания схемы ещё пуст).
 * `by` — имя поля или функция, извлекающая сравниваемое значение.
 * `ignore` — значение, которое надо не учитывать (при редактировании
 * сам элемент совпадает сам с собой).
 */
export const unique =
  (list, { by = 'name', message = 'Такое значение уже существует', ignore = null } = {}) =>
  (value) => {
    if (isEmpty(value)) return true

    const items = typeof list === 'function' ? list() : list
    if (!Array.isArray(items)) return true

    const extract = typeof by === 'function' ? by : (item) => item?.[by]
    const needle = String(value).trim().toLowerCase()
    const ignored = ignore == null ? null : String(typeof ignore === 'function' ? ignore() : ignore).trim().toLowerCase()

    const clash = items.some((item) => {
      const candidate = extract(item)
      if (candidate === null || candidate === undefined) return false
      const text = String(candidate).trim().toLowerCase()

      return text === needle && text !== ignored
    })

    return clash ? message : true
  }

/** Произвольная проверка: `fn(value, values)` возвращает true | строка. */
export const custom = (fn, message = 'Некорректное значение') => (value, values) => {
  const result = fn(value, values)

  if (result === true || result === undefined || result === null || result === '') return true

  return typeof result === 'string' ? result : message
}
