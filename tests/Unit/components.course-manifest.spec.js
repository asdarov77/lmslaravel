// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { readFileSync } from 'node:fs'

/**
 * Страница материала курса (CourseManifest).
 *
 * Регрессии, которые закрывает файл:
 *
 *  1. КРИТИЧНО. Кнопка «вверх» показывалась через v-if: при каждом
 *     своём появлении Vue пересоздавал iframe, документ материала
 *     на мгновение становился пустым, высота схлопывалась до ~172px,
 *     а прокрутка сбрасывалась в ноль. Усиливал эффект scroll-behavior:
 *     smooth — анимированная прокрутка дёргала состояние десятки раз в
 *     секунду. Это и было «ничего не отображается».
 *
 *  2. Высота кадра задавалась инлайновым onload, писавшим высоту прямо в
 *     DOM, а Vue на каждом ре-рендере перезаписывал :style пустым
 *     значением. Само по себе это редко проявлялось, но оставляло две
 *     рассинхронизации: источник высоты был один — DOM — и он же
 *     затирался. Теперь высота живёт в состоянии.
 *
 *  3. Переключатели панелей гасили друг друга и само дерево, из-за чего
 *     нажатие на «лупу» или «сердечко» выглядело как поломка.
 *
 *  4. Подсветка при наведении делалась через document.getElementById на
 *     каждом движении мыши (182 узла) — это и тормозило отрисовку,
 *     и не работало корректно.
 */

const get = vi.hoisted(() => vi.fn())
vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get, post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'

import CourseModule from '../../resources/js/Store/modules/CourseModule'
import AuthModule from '../../resources/js/Store/modules/AuthModule'
import ru from '../../resources/js/locales/ru.json'
import CourseManifest from '../../resources/js/Pages/CourseManifest.vue'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const source = readFileSync(
  resolve(dirname(fileURLToPath(import.meta.url)), '../../resources/js/Pages/CourseManifest.vue'),
  'utf8'
)

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, locale: 'ru', messages: { ru } })

const envelope = data => ({ data: { success: true, data, error: null, meta: null } })

/** Разделы/подразделы/модули: типы 0..3. */
const aukstructures = [
  { id: 100, parent_id: 0, title: 'Курс', type: 0 },
  { id: 101, parent_id: 100, title: 'Раздел', type: 1 },
  { id: 102, parent_id: 101, title: 'Подраздел', type: 2 },
  { id: 103, parent_id: 102, title: 'Модуль первый', type: 3 },
  { id: 104, parent_id: 102, title: 'Модуль второй', type: 3 },
]

let store
let consoleErrors
let originalError

const tick = () => new Promise(r => setTimeout(r, 0))

const mountPage = () =>
  mount(CourseManifest, {
    propsData: { idEdit: 16, idCategory: 6 },
    global: {
      plugins: [i18n],
      mocks: {
        $store: store,
        $route: { query: {} },
        $router: { push: vi.fn(), go: vi.fn() },
      },
      stubs: {
        'v-progress-linear': true,
        'v-progress-circular': true,
        popup: true,
      },
    },
  })

beforeEach(() => {
  vi.clearAllMocks()
  window.localStorage.clear()
  consoleErrors = []
  originalError = console.error
  console.error = (...a) => consoleErrors.push(a.map(String).join(' '))

  store = createStore({
    modules: {
      Course: CourseModule,
      Auth: { ...AuthModule, getters: { ...AuthModule.getters, isSuperAdmin: () => false } },
    },
  })

  get.mockImplementation(url => {
    if (url.includes('/api/course')) return Promise.resolve(envelope([{ title: 'Курс', aukstructures }]))
    if (url.includes('/api/getlink/')) {
      return Promise.resolve(envelope({ aircraft: 'КЛЕН', auk: '01', file: 'a.html' }))
    }
    if (url.includes('/api/private/signed-url')) {
      return Promise.resolve(envelope({ base: 'api/private/КЛЕН/01/1/abc' }))
    }
    if (url.includes('/api/favorites')) return Promise.resolve(envelope({ favorites: [] }))
    return Promise.resolve(envelope([]))
  })
})

afterEach(() => {
  console.error = originalError
})

describe('CourseManifest: высота кадра не схлопывается', () => {
  it('высота задаётся через @load, а не инлайновым onload', () => {
    // Источник высоты был один — инлайновый onload писал её прямо в DOM,
    // а :style="{ height: '' }" на том же узле её же затирал при
    // перерисовке. Теперь единственный источник — состояние.
    expect(source).toContain('@load="onFrameLoad"')
    expect(source).not.toContain("onload=\"try{this.style.height")
    expect(source).toContain('height: frameHeight')
  })

  it('высота держится в состоянии, а не в DOM', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    // Значение по умолчанию, до загрузки документа.
    expect(typeof wrapper.vm.frameHeight).toBe('string')
    expect(wrapper.vm.frameHeight).not.toBe('')
    expect(wrapper.html()).not.toContain('height: ;')
  })

  it('onload кадра выставляет высоту по содержимому', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const setFrameHeight = height => {
      wrapper.vm.onFrameLoad({ target: { contentDocument: { body: { scrollHeight: height } } } })
    }

    setFrameHeight(4508)
    expect(wrapper.vm.frameHeight).toBe('4528px')

    // Повторная установка той же высоты ничего не ломает.
    setFrameHeight(4508)
    expect(wrapper.vm.frameHeight).toBe('4528px')
  })

  it('пустой документ не сбрасывает высоту материала', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.onFrameLoad({ target: { contentDocument: { body: { scrollHeight: 4508 } } } })
    wrapper.vm.onFrameLoad({ target: { contentDocument: null } })

    expect(wrapper.vm.frameHeight).toBe('4528px')
  })

  it('доступ к документу кадра не ломает страницу', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.frameHeight = '1000px'
    expect(() =>
      wrapper.vm.onFrameLoad({
        target: {
          get contentDocument() {
            throw new Error('sandbox')
          },
        },
      })
    ).not.toThrow()

    expect(wrapper.vm.frameHeight).toBe('1000px')
  })
})

describe('CourseManifest: подсветка найденного', () => {
  /*
   * Регресс, который закрывает этот блок: подсветка применялась через
   * `setTimeout(1000)` и переписыванием `iframeDoc.body.innerHTML` по
   * результату DOMParser. Пересоздание всего DOM заново гоняло картинки
   * и сбрасывало прокрутку — на большом материале страница подвисала, а
   * таймер успевал до готовности кадра мимо.
   */
  it('loadContent копит подсветку и не ждёт таймер', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const getlink = vi.spyOn(wrapper.vm, 'getlink').mockResolvedValue()
    wrapper.vm.loadContent(103, [{ originalXpath: '//p', highlightedText: '<b>x</b>' }])

    expect(wrapper.vm.pendingHighlights).toHaveLength(1)
    expect(getlink).toHaveBeenCalledWith(103)

    getlink.mockRestore()
    wrapper.unmount()
  })

  it('не-Массив в выделении не роняет открытие материала', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const getlink = vi.spyOn(wrapper.vm, 'getlink').mockResolvedValue()
    expect(() => wrapper.vm.loadContent(103, undefined)).not.toThrow()
    expect(wrapper.vm.pendingHighlights).toEqual([])

    getlink.mockRestore()
    wrapper.unmount()
  })

  it('applyHighlights правит только ветку найденного узла', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const doc = document.implementation.createHTMLDocument('mat')
    doc.body.innerHTML = '<p id="hit">найдено тут</p><div id="keep">не трогать</div>'

    // Сервер отдаёт путь к ТЕКСТОВОМУ узлу и его содержимое, разбитое
    // вокруг совпадения. Раньше здесь подменялся родитель целиком,
    // и все его остальные дети исчезали.
    wrapper.vm.pendingHighlights = [
      { originalXpath: '/html/body/p/text()', highlightedText: 'найдено <mark class="highlighted">тут</mark>' },
    ]
    wrapper.vm.applyHighlights(doc)

    expect(doc.querySelector('#hit .highlighted').textContent).toBe('тут')
    expect(doc.querySelector('#hit').textContent).toBe('найдено тут')
    // Главный признак: остальной документ не пересоздан.
    expect(doc.querySelector('#keep').textContent).toBe('не трогать')
    expect(wrapper.vm.pendingHighlights).toEqual([])
  })

  it('несовпавший XPath оставляет материал открытым, а не ломает его', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const doc = document.implementation.createHTMLDocument('mat')
    doc.body.innerHTML = '<p>материал</p>'

    wrapper.vm.pendingHighlights = [
      { originalXpath: '//table[@id="нет-такого"]', highlightedText: '<b>x</b>' },
    ]

    expect(() => wrapper.vm.applyHighlights(doc)).not.toThrow()
    expect(doc.querySelector('p').textContent).toBe('материал')
  })

  it('отсутствие совпадений не роняет прокрутку', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    // Раньше здесь стояло node.getBoundingClientRect() без проверки,
    // и клик по результату без подсветки давал TypeError.
    expect(() => wrapper.vm.scrollToHighlight(undefined)).not.toThrow()
  })

  it('подсветка применяется при загрузке кадра', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const doc = document.implementation.createHTMLDocument('mat')
    doc.body.innerHTML = '<p id="hit">найдено тут</p>'

    wrapper.vm.pendingHighlights = [
      { originalXpath: '/html/body/p/text()', highlightedText: 'найдено <mark class="highlighted">тут</mark>' },
    ]
    wrapper.vm.onFrameLoad({ target: { contentDocument: doc } })

    expect(wrapper.vm.pendingHighlights).toEqual([])
    expect(wrapper.vm.highlighted.length).toBe(1)
  })
})

describe('CourseManifest: оформление не течёт на другие страницы', () => {
  it('не перекрашивает карточки Vuetify во всём приложении', () => {
    const css = readFileSync('resources/js/Pages/CourseManifest.vue', 'utf-8')

    // Регресс: unscoped-правило `.v-card { border: 1px solid lightgrey }`
    // меняло рамку каждой карточки на всех страницах.
    expect(css).not.toMatch(/^\.v-card\s*\{/m)
    expect(css).not.toContain('lightgrey')
  })

  it('подсветка взята из токенов, а не из палитры браузера', () => {
    const css = readFileSync('resources/js/Pages/CourseManifest.vue', 'utf-8')

    expect(css).not.toMatch(/background-color:\s*yellow/)
  })
})

describe('CourseManifest: посещённые материалы', () => {
  it('открытый модуль помечается посещённым и переживает перезагрузку', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.markVisited(103)
    expect(wrapper.vm.isVisited(103)).toBe(true)
    expect(wrapper.vm.isVisited(104)).toBe(false)

    const stored = JSON.parse(window.localStorage.getItem('course-manifest-visited:16'))
    expect(stored).toEqual([103])
  })

  it('прогресс разных курсов не смешивается', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.markVisited(103)
    expect(wrapper.vm.visitedKey()).toBe('course-manifest-visited:16')
  })

  it('битое значение в localStorage не ломает страницу', async () => {
    window.localStorage.setItem('course-manifest-visited:16', '{не json')

    const wrapper = mountPage()
    await tick()
    await tick()

    // Первый модуль открывается автоматически и честно помечается
    // посещённым — важно лишь, что мусор из хранилища не попал в список.
    expect(Array.isArray(wrapper.vm.visitedIds)).toBe(true)
    expect(wrapper.vm.visitedIds.every((id) => Number.isInteger(id))).toBe(true)
  })

  it('повторное открытие не дублирует запись', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.markVisited(103)
    wrapper.vm.markVisited(103)

    expect(wrapper.vm.visitedIds).toEqual([103])
  })
})

describe('CourseManifest: стиль узла дерева', () => {
  it('inline nodeStyle удалён: уровни узла на классах TreeView', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    // P1.4: паддинг и кегль зависит от level через --level-классы
    // TreeViewNode, а не inline-стили (inline перерисовывают все узлы
    // при смене активного).
    expect(wrapper.vm.nodeStyle).toBeUndefined()
    const TreeView = (await import('../../resources/js/components/ui/TreeViewNode.vue')).default
    expect(TreeView).toBeTruthy()
  })

  it('узлы дерева не завязаны на document.getElementById', async () => {
    // Подсветка при наведении переехала на CSS: обработчик на каждом
    // узле трогал DOM при каждом движении мыши.
    const wrapper = mountPage()
    await tick()
    await tick()

    const html = wrapper.html()
    expect(html).not.toContain('@mouseover="item.type === 3 ? showthumb')
    expect(html).not.toContain('hidethumb')
  })
})

describe('CourseManifest: панели не исключают друг друга', () => {
  it('переключатель поиска не гасит дерево', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.toggleSearch()
    expect(wrapper.vm.showSearch).toBe(true)
    expect(wrapper.vm.showItems, 'дерево осталось на месте').toBe(true)
    expect(wrapper.vm.isFavorite).toBe(false)
  })

  it('переключатель избранного не гасит дерево и поиск', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.showSearch = true
    wrapper.vm.toggleFavorite()

    expect(wrapper.vm.isFavorite).toBe(true)
    expect(wrapper.vm.showItems).toBe(true)
    expect(wrapper.vm.showSearch).toBe(true)
  })

  it('кнопка дерева не трогает остальные панели', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    wrapper.vm.isFavorite = true
    wrapper.vm.toggleList()

    expect(wrapper.vm.showItems).toBe(false)
    expect(wrapper.vm.isFavorite).toBe(true)
  })
})

describe('CourseManifest: прокрутка', () => {
  it('кнопка «вверх» использует v-show, а не v-if', async () => {
    // v-if пересоздавал iframe при каждом появлении кнопки — материал
    // схлопывался до пустого кадра.
    const wrapper = mountPage()
    await tick()
    await tick()

    expect(source).toMatch(/v-show="canScrollUp"/)
    expect(source).not.toMatch(/v-if="canScrollUp"/)
  })

  it('скролл контейнера переключает состояние кнопки', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    // jsdom не верстает layout, поэтому scrollTop задаём вручную.
    const el = wrapper.vm.$refs.contentEl?.$el ?? wrapper.vm.$refs.contentEl
    expect(el, 'нужен реальный узел контейнера').toBeTruthy()

    let top = 0
    Object.defineProperty(el, 'scrollTop', { get: () => top, set: (v) => (top = v), configurable: true })

    wrapper.vm.bindScroll()
    expect(wrapper.vm.canScrollUp).toBe(false)

    el.scrollTop = 900
    el.dispatchEvent(new Event('scroll'))
    await new Promise(r => setTimeout(r, 60))

    expect(wrapper.vm.canScrollUp).toBe(true)
  })

  it('кнопка «вверх» прокручивает контейнер в начало', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    const el = wrapper.vm.$refs.contentEl?.$el ?? wrapper.vm.$refs.contentEl
    let scrolled = null
    el.scrollTo = (opts) => (scrolled = opts)

    wrapper.vm.scrollToTop()

    expect(scrolled).toEqual({ top: 0, behavior: 'smooth' })
  })
})

describe('CourseManifest: без ошибок в консоли', () => {
  it('страница монтируется и грузит дерево без исключений', async () => {
    mountPage()
    await tick()
    await tick()
    await tick()

    const fatal = consoleErrors.filter((line) => !/Failed to load resource/.test(line))
    expect(fatal, `ошибки в консоли: ${fatal.join('; ')}`).toEqual([])
  })
})

/*
 * Фаза 3.4: страница материала перестала быть источником истины по
 * цвету.
 *
 * Линтер столбов относит CourseManifest к файлам-наследии и потому
 * лишь предупреждает о hex-цветах, а не запрещает их. Проверка жёстче:
 * страница должна держаться на токенах, иначе в тёмной теме панель
 * инструментов и подсветка миниатюры остаются светлыми пятнами.
 */
describe('CourseManifest: цвет только из токенов', () => {
  const read = () => readFileSync('resources/js/Pages/CourseManifest.vue', 'utf-8')

  it('не содержит hex-цветов в стилях и скрипте', () => {
    const source = read()
    // HTML-сущности вроде &#9650; — это стрелки, а не цвета.
    const hex =
      source.match(/#[0-9a-fA-F]{3,6}\b/g)?.filter((m) => !source.includes(`&#${m.slice(1)};`)) ?? []

    expect(hex, `найдены hex-цвета: ${hex.join(', ')}`).toHaveLength(0)
  })

  it('панель инструментов окрашена токенами', () => {
    const source = read()
    const toolbar = source.slice(source.indexOf('.cm-toolbar {'), source.indexOf('.cm-toolbar__group'))

    expect(toolbar).toContain('var(--c-border)')
    expect(toolbar).toContain('var(--c-surface-2)')
  })

  it('мёртвые методы подсветки миниатюры удалены', () => {
    // showthumb/hidethumb нигде не вызывались и только динамически
    // красили узлы в серый — в тёмной теме это было серое пятно.
    const source = read()

    expect(source).not.toContain('showthumb')
    expect(source).not.toContain('hidethumb')
  })
})
