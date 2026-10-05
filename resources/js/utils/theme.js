/**
 * Переключатель темы.
 *
 * Отдельный модуль, а не флаг в UiModule, потому что выбор темы должен
 * применяться ДО первой отрисовки: иначе страница сначала показывается
 * светлой и мигает. Поэтому здесь три вещи:
 *
 *  1) STORAGE_KEY и сохранённое значение — точка входа для app.blade.php,
 *     который ставит data-theme на <html> инлайновым скриптом;
 *  2) preferred() — системная настройка, если пользователь ещё не
 *     выбирал тему вручную (иначе чужой системный тёмный режим
 *     игнорировался бы);
 *  3) apply() синхронизирует атрибут на <html> и тему Vuetify: наши
 *     CSS-токены живут под .v-theme--dark, а Vuetify вешает этот класс
 *     на корневой элемент приложения. Одно без другого оставляет
 *     страницу наполовину тёмной.
 */

const STORAGE_KEY = 'ui-theme'

/** 'light' | 'dark' | 'auto' */
const read = () => {
  try {
    const value = window.localStorage.getItem(STORAGE_KEY)

    return value === 'dark' || value === 'light' || value === 'auto' ? value : 'auto'
  } catch (error) {
    // Приватный режим и отключённое хранилище: работаем без
    // сохранения, а не падаем.
    return 'auto'
  }
}

const write = (mode) => {
  try {
    window.localStorage.setItem(STORAGE_KEY, mode)
  } catch (error) {
    /* см. read() */
  }
}

const systemPrefersDark = () => {
  if (typeof window === 'undefined' || !window.matchMedia) return false

  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

/** Какой теме соответствует текущий выбор. */
const resolve = (mode) => (mode === 'auto' ? (systemPrefersDark() ? 'dark' : 'light') : mode)

/**
 * Применить тему.
 *
 * @param {'light' | 'dark'} theme
 * @param {object|null} vuetify Экземпляр Vuetify ИЛИ его тема —
 *   не обязателен: на первом проходе тему ставит app.blade.php, когда
 *   Vuetify ещё не создан.
 *
 * Принимаются обе формы: сам vuetify (`vuetify.theme.global`) и то,
 * что отдаёт `useTheme()` (`theme.global`). Раньше проверялся только
 * первый, и переключение «вживую» не срабатывало: карточки темнели
 * (наши токены живут под .v-theme--dark на <html>), а фон и текст
 * оставались светлыми до перезагрузки.
 */
const apply = (theme, vuetify = null) => {
  const dark = theme === 'dark'

  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', theme)
    // Класс на <html> нужен нашим токенам: .v-theme--dark объявлен
    // в tokens.css именно под этим классом, а Vuetify вешает его на
    // корневой элемент приложения, который появляется позже.
    document.documentElement.classList.toggle('v-theme--dark', dark)
  }

  const global = vuetify?.theme?.global ?? vuetify?.global ?? null

  if (global?.name) {
    global.name.value = theme
  }
}

/**
 * Инициализация: восстановить сохранённый выбор и подписаться на
 * смену системной темы при режиме «auto».
 */
const init = (vuetify) => {
  const mode = read()

  apply(resolve(mode), vuetify)

  if (typeof window === 'undefined' || !window.matchMedia) return

  const query = window.matchMedia('(prefers-color-scheme: dark)')

  const onChange = () => {
    // Реагируем только в режиме auto: явный выбор пользователя важнее
    // системной настройки.
    if (read() === 'auto') apply(resolve('auto'), vuetify)
  }

  if (query.addEventListener) query.addEventListener('change', onChange)
  else query.addListener(onChange)
}

/**
 * Переключить выбор: light -> dark -> auto -> light.
 *
 * Три состояния, а не два, потому что «auto» иначе приходилось бы
 * выбирать вручную тем, кто хочет следовать системе.
 */
const cycle = (vuetify) => {
  const order = ['light', 'dark', 'auto']
  const current = read()
  const next = order[(order.indexOf(current) + 1) % order.length]

  write(next)
  apply(resolve(next), vuetify)

  return next
}

const set = (mode, vuetify) => {
  write(mode)
  apply(resolve(mode), vuetify)

  return mode
}

export default {
  STORAGE_KEY,
  read,
  write,
  resolve,
  apply,
  init,
  cycle,
  set,
  systemPrefersDark,
}

export { STORAGE_KEY, read, write, resolve, apply, init, cycle, set, systemPrefersDark }
