// @vitest-environment jsdom
/**
 * Тема оформления: переключение, сохранение, «как в системе».
 *
 * Закрывает то, что ломало тёмную тему по построению:
 *  - Vuetify читал токены из :root, то есть для ТЁМНОЙ темы получал
 *    СВЕТЛУЮ палитру при `dark: true`; теперь токены читаются из
 *    элемента с классом .v-theme--dark;
 *  - apply() принимал только сам экземпляр Vuetify, а компонент
 *    передаёт то, что отдаёт useTheme(); из-за этого переключение
 *    «вживую» меняло наши токены, но не тему Vuetify — карточки
 *    темнели, фон и текст оставались светлыми до перезагрузки;
 *  - режим читался из localStorage внутри computed, а у localStorage
 *    нет реактивности: иконка не менялась до перезагрузки;
 *  - тема обязана применяться ДО первой отрисовки, иначе страница
 *    мигает светлой (это делает инлайновый скрипт в app.blade.php —
 *    его ключ проверяется здесь же).
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import theme from '../../resources/js/utils/theme'

/** Тема-объект в форме, которую отдаёт useTheme(). */
const makeTheme = () => ({ global: { name: { value: 'light' } } })

beforeEach(() => {
  localStorage.clear()
  document.documentElement.removeAttribute('data-theme')
  document.documentElement.classList.remove('v-theme--dark')
  // По умолчанию система светлая — иначе результат зависит от
  // настроек машины, где тесты запускаются.
  window.matchMedia = vi.fn().mockReturnValue({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
  })
})

describe('theme: чтение и запись выбора', () => {
  it('по умолчанию auto, а не light', () => {
    expect(theme.read()).toBe('auto')
  })

  it('принимает только известные значения', () => {
    localStorage.setItem('ui-theme', 'dark')
    expect(theme.read()).toBe('dark')

    localStorage.setItem('ui-theme', 'светлая')
    expect(theme.read()).toBe('auto')
  })

  it('не падает при недоступном хранилище', () => {
    // Приватный режим: localStorage бросает исключение, и страница
    // не должна из-за этого не грузиться.
    const spy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied')
    })

    expect(theme.read()).toBe('auto')

    spy.mockRestore()
  })
})

describe('theme: разрешение режима', () => {
  it('auto следует системной настройке', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: true })
    expect(theme.resolve('auto')).toBe('dark')

    window.matchMedia = vi.fn().mockReturnValue({ matches: false })
    expect(theme.resolve('auto')).toBe('light')
  })

  it('явный выбор важнее системной настройки', () => {
    window.matchMedia = vi.fn().mockReturnValue({ matches: true })
    expect(theme.resolve('light')).toBe('light')
  })
})

describe('theme: применение', () => {
  it('ставит атрибут и класс на <html>', () => {
    theme.apply('dark')

    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(document.documentElement.classList.contains('v-theme--dark')).toBe(true)

    theme.apply('light')

    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
    expect(document.documentElement.classList.contains('v-theme--dark')).toBe(false)
  })

  it('переключает тему Vuetify по форме useTheme()', () => {
    // Именно эту форму передаёт компонент кнопки. Раньше проверялся
    // только vuetify.theme.global, и живое переключение не срабатывало.
    const instance = makeTheme()
    theme.apply('dark', instance)
    expect(instance.global.name.value).toBe('dark')
  })

  it('переключает тему Vuetify и по форме самого vuetify', () => {
    const instance = { theme: { global: { name: { value: 'light' } } } }
    theme.apply('dark', instance)
    expect(instance.theme.global.name.value).toBe('dark')
  })

  it('работает без vuetify (первый проход до его создания)', () => {
    expect(() => theme.apply('dark', null)).not.toThrow()
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })
})

describe('theme: цикл переключения', () => {
  it('light -> dark -> auto -> light', () => {
    const instance = makeTheme()

    theme.set('light', instance)
    expect(theme.cycle(instance)).toBe('dark')
    expect(theme.cycle(instance)).toBe('auto')
    expect(theme.cycle(instance)).toBe('light')
  })

  it('переключение применяется и сохраняется', () => {
    const instance = makeTheme()

    theme.set('light', instance)
    theme.cycle(instance)

    expect(theme.read()).toBe('dark')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(instance.global.name.value).toBe('dark')
  })
})

describe('theme: инициализация', () => {
  it('восстанавливает сохранённую тему', () => {
    localStorage.setItem('ui-theme', 'dark')
    const instance = makeTheme()

    theme.init(instance)

    expect(instance.global.name.value).toBe('dark')
  })

  it('учитывает системную настройку при первом визите', () => {
    window.matchMedia = vi.fn().mockReturnValue({
      matches: true,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
    })
    const instance = makeTheme()

    theme.init(instance)

    expect(instance.global.name.value).toBe('dark')
  })

  it('подписывается на смену системной темы в режиме auto', () => {
    const addEventListener = vi.fn()
    window.matchMedia = vi.fn().mockReturnValue({
      matches: false,
      addEventListener,
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
    })

    theme.init(makeTheme())

    expect(addEventListener).toHaveBeenCalledWith('change', expect.any(Function))
  })
})

describe('theme: токены', () => {
  it('в tokens.css описан набор тёмных токенов', () => {
    const css = readFileSync('resources/css/tokens.css', 'utf-8')
    const dark = css.slice(css.indexOf('.v-theme--dark'))

    for (const token of ['--c-bg', '--c-surface', '--c-text', '--c-primary', '--c-on-primary', '--c-border']) {
      expect(dark, `тёмная тема должна переопределять ${token}`).toContain(`${token}:`)
    }
  })

  it('тёмная тема отличается от светлой по значениям', () => {
    const css = readFileSync('resources/css/tokens.css', 'utf-8')
    const lightSurface = css.match(/--c-surface:\s*([^;]+);/)[1].trim()
    const darkPart = css.slice(css.indexOf('.v-theme--dark'))
    const darkSurface = darkPart.match(/--c-surface:\s*([^;]+);/)[1].trim()

    expect(darkSurface).not.toBe(lightSurface)
  })

  it('собственные стили подключаются после vuetify/styles', () => {
    // При обратном порядке Vuetify перебивает наши правила при равной
    // специфичности: чип оставался белым на светло-синем фоне.
    const source = readFileSync('resources/js/app.js', 'utf-8')
    const vuetifyAt = source.indexOf("import 'vuetify/styles'")
    const tokensAt = source.indexOf("import '../css/tokens.css'")
    const appAt = source.indexOf("import '../css/app.css'")

    expect(vuetifyAt).toBeGreaterThan(-1)
    expect(tokensAt).toBeGreaterThan(vuetifyAt)
    expect(appAt).toBeGreaterThan(vuetifyAt)
  })

  it('тема ставится инлайном до отрисовки, иначе страница мигает', () => {
    const blade = readFileSync('resources/views/app.blade.php', 'utf-8')
    const scriptAt = blade.indexOf("var key = 'ui-theme'")
    const viteAt = blade.indexOf('@vite')

    expect(scriptAt, 'в blade должен быть инлайновый скрипт темы').toBeGreaterThan(-1)
    expect(scriptAt, 'скрипт темы идёт до @vite').toBeLessThan(viteAt)
    expect(blade).toContain('prefers-color-scheme: dark')
  })

  it('в бренде и полях нет зашитых цветов вместо токенов', () => {
    const css = readFileSync('resources/css/app.css', 'utf-8')
    const brand = css.slice(css.indexOf('.app__brand'), css.indexOf('.app__brand') + 400)

    expect(brand).toContain('var(--c-on-primary)')
    expect(brand).not.toMatch(/color:\s*#fff/)

    const edit = readFileSync('resources/js/Pages/User/UserItemEdit.vue', 'utf-8')
    expect(edit).not.toContain('background-color: #f4f4f4')
    expect(edit).toContain('var(--c-surface-3)')
  })
})

describe('theme: кнопка переключения', () => {
  it('объявлена в шапке', () => {
    const app = readFileSync('resources/js/App.vue', 'utf-8')
    expect(app).toContain('<theme-toggle')
    expect(app).toContain("import ThemeToggle from './components/ui/ThemeToggle.vue'")
  })

  it('хранит режим в ref, а не читает localStorage в computed', () => {
    // У localStorage нет реактивности: подпись и иконка оставались
    // прежними до перезагрузки страницы.
    const source = readFileSync('resources/js/components/ui/ThemeToggle.vue', 'utf-8')

    expect(source).toContain('ref(theme.read())')
    expect(source).not.toMatch(/computed\(\(\)\s*=>\s*theme\.read\(\)\)/)
  })

  it('помечена для скринридера', () => {
    const source = readFileSync('resources/js/components/ui/ThemeToggle.vue', 'utf-8')
    expect(source).toContain(':aria-label="label"')
    expect(source).toContain(':aria-pressed="isDark"')
    expect(source).toContain('data-test="theme-toggle"')
  })
})
