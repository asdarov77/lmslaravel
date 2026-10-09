/**
 * Плотность интерфейса.
 *
 * Отдельный модуль, а не флаг только в Vuex, по той же причине, что и
 * у темы: выбор должен применяться к <html> до/вне рендера, чтобы
 * таблицы и формы не «дёргались» между обычной и компактной плотностью.
 *
 * Переключатель в шапке раньше менял флаг в Vuex, но НИКТО не читал
 * его и не менял DOM: нажатие не давало никакого эффекта, а в шаблоне
 * использовались несуществующие `density`/`toggleDensity`. Здесь
 * выбор доводится до корня документа атрибутом data-density, на
 * который опираются стили в app.css.
 */

const STORAGE_KEY = 'ui-density'

const VALID = ['default', 'compact']

/** 'default' | 'compact' */
const read = () => {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY)

    return VALID.includes(value) ? value : 'default'
  } catch (error) {
    // Приватный режим: работаем без сохранения, а не падаем.
    return 'default'
  }
}

const write = (mode) => {
  try {
    window.localStorage.setItem(STORAGE_KEY, mode)
  } catch (error) {
    /* см. read() */
  }
}

/** Проставить атрибут data-density на <html>. */
const apply = (mode) => {
  if (typeof document === 'undefined') return mode

  const value = VALID.includes(mode) ? mode : 'default'
  document.documentElement.setAttribute('data-density', value)

  return value
}

/** Восстановить сохранённый выбор и применить его. */
const init = () => apply(read())

/** Переключить default <-> compact, сохранить и применить. */
const toggle = () => {
  const next = read() === 'compact' ? 'default' : 'compact'
  write(next)

  return apply(next)
}

export default { STORAGE_KEY, read, write, apply, init, toggle }
export { STORAGE_KEY, read, write, apply, init, toggle }
