/**
 * Маски ввода.
 *
 * Зачем: ФИО, телефон, дата и ИНН вводились как обычный текст, и
 * каждый пользователь форматировал их по-своему («+7 999...»,
 * «8-999-...», «9991234567»). На бэкенд уходило то, что набрали, и
 * часть проверок не срабатывала.
 *
 * Правило-шаблон: `9` — цифра, `A` — буква, `*` — любой символ,
 * остальные символы шаблона — литералы, которые подставляются сами.
 *
 * `applyMask` идемпотентна: повторное применение к уже
 * отформатированной строке даёт тот же результат.
 */

export const MASK_PRESETS = {
  phone: '+7 (999) 999-99-99',
  date: '99.99.9999',
  inn: '999999999999',
  snils: '999-999-999 99',
  passport: '99 99 999999',
}

const isDigit = (ch) => ch >= '0' && ch <= '9'
const isLetter = (ch) => /[A-Za-zА-Яа-яЁё]/.test(ch)

/**
 * Наложить маску на значение.
 *
 * @param {string|number} value
 * @param {string} pattern
 * @returns {string}
 */
export function applyMask(value, pattern) {
  if (!pattern) return value == null ? '' : String(value)

  const source = value == null ? '' : String(value)
  let result = ''
  let index = 0

  for (const token of pattern) {
    // Входные символы закончились — не дописываем «хвостовые» литералы
    // шаблона (иначе незаконченный номер выглядел бы как «+7 (»).
    if (index >= source.length) break

    const ch = source[index]

    if (token === '9') {
      if (isDigit(ch)) {
        result += ch
        index += 1
      } else {
        index += 1
      }
    } else if (token === 'A') {
      if (isLetter(ch)) {
        result += ch
        index += 1
      } else {
        index += 1
      }
    } else if (token === '*') {
      result += ch
      index += 1
    } else if (ch === token) {
      result += ch
      index += 1
    } else {
      // Литерал шаблона подставляем, входной символ не расходуем.
      result += token
    }
  }

  return result
}

/** Только цифры, не длиннее limit (если задан). */
export function digitsOnly(value, limit = null) {
  const digits = String(value ?? '').replace(/\D/g, '')

  return limit ? digits.slice(0, limit) : digits
}

/** Убрать все символы-разделители маски, оставив значимые. */
export function unmask(value, pattern = null) {
  if (!pattern) return String(value ?? '').replace(/[^\dA-Za-zА-Яа-яЁё]/g, '')

  const source = String(value ?? '')
  let result = ''
  let index = 0

  for (const token of pattern) {
    if (index >= source.length) break
    const ch = source[index]

    if (token === '9') {
      if (isDigit(ch)) result += ch
      index += 1
    } else if (token === 'A') {
      if (isLetter(ch)) result += ch
      index += 1
    } else if (token === '*') {
      result += ch
      index += 1
    } else if (ch === token) {
      index += 1
    } else {
      index += 1
    }
  }

  return result
}

export default { MASK_PRESETS, applyMask, digitsOnly, unmask }
