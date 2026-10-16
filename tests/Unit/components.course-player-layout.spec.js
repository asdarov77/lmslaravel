// @vitest-environment jsdom
/**
 * CoursePlayerLayout (Фаза 3.4) — каркас просмотра курса, выделенный
 * из монолита CourseManifest.vue.
 *
 * Проверяем то, чего раньше не существовало: прогресс курса в шапке,
 * отметки прочтения разделов, переходы «предыдущий/следующий» и
 * полноэкранный режим. Плюс регресс на отступ по уровню вложенности —
 * без него разделы второго уровня сливались с первым.
 */
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import CoursePlayerLayout from '../../resources/js/components/ui/CoursePlayerLayout.vue'
import ru from '../../resources/js/locales/ru.json'
import { readFileSync } from 'node:fs'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru },
})

/**
 * Дерево курса в исходном порядке: тема → раздел → модули.
 *
 * isMaterial различает заголовок структуры и материал. Раньше разницы не
 * было: все узлы рендерились как <button> с курсором-указателем, и клик
 * по теме выглядел как переход, хотя открывать было нечего.
 */
const SECTIONS = [
  { id: 1, title: 'Тема 1. Основы', state: 'done', level: 1, isMaterial: false },
  { id: 2, title: 'Раздел 1.1 Устройство', state: 'partial', level: 2, isMaterial: false },
  { id: 3, title: 'Введение', state: 'done', level: 3, isMaterial: true },
  { id: 4, title: 'Устройство самолёта', state: 'todo', level: 3, isMaterial: true },
  { id: 5, title: 'Двигатель', state: 'todo', level: 3, isMaterial: true },
]

const MATERIALS = SECTIONS.filter((section) => section.isMaterial)

const mountLayout = (props = {}) =>
  mount(CoursePlayerLayout, {
    props: {
      courseTitle: 'Пилотская программа',
      sections: SECTIONS,
      activeId: 2,
      progress: 37.5,
      ...props,
    },
    global: { plugins: [vuetify, i18n] },
  })

describe('CoursePlayerLayout: прогресс курса', () => {
  it('показывает процент и число прочитанных разделов', () => {
    const wrapper = mountLayout()

    const bar = wrapper.find('[role="progressbar"]')
    expect(bar.exists()).toBe(true)
    expect(bar.attributes('aria-valuenow')).toBe('38')

    // Считаются МАТЕРИАЛЫ, а не узлы дерева. Тема «Тема 1. Основы»
    // помечена прочитанной (её материал прочитан), но в счётчик она не
    // входит: перехода за заголовком нет, и «прочитано 2 из 3» вводило
    // бы в заблуждение. Второй подписи «38%» без неё не отвечает, сколько
    // осталось.
    expect(wrapper.text()).toContain('прочитано 1 из 3')

    wrapper.unmount()
  })

  it('передаёт корректные значения в ARIA', () => {
    const wrapper = mountLayout({ progress: 42 })

    const bar = wrapper.find('[role="progressbar"]')
    expect(bar.attributes('aria-valuemin')).toBe('0')
    expect(bar.attributes('aria-valuemax')).toBe('100')
    expect(bar.attributes('aria-valuenow')).toBe('42')

    wrapper.unmount()
  })

  it('ограничивает прогресс сверху, чтобы заливка не вылезла за трек', () => {
    // Значение из бэкенда может оказаться больше 100 — тогда полоса
    // вылезала бы за пределы дорожки. Обрезаем.
    const over = mountLayout({ progress: 140 })
    expect(over.find('.cpl__progress-fill').attributes('style')).toContain('width: 100%')
    over.unmount()
  })

  it('при нулевом прогрессе шкалу не рисует вовсе', () => {
    // Пустой трек с надписью «0%» только шумит; при этом в полном
    // экране шкала остаётся, чтобы ориентироваться, где находишься.
    const none = mountLayout({ progress: -20 })
    expect(none.find('[role="progressbar"]').exists()).toBe(false)
    none.unmount()

    const fullscreen = mountLayout({ progress: -20, isFullscreen: true })
    expect(fullscreen.find('[role="progressbar"]').exists()).toBe(true)
    fullscreen.unmount()
  })

  it('не показывает прогресс, когда его нет', () => {
    const wrapper = mountLayout({ progress: 0 })
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: содержание', () => {
  it('рисует материалы кнопками, а темы и разделы — заголовками', () => {
    const wrapper = mountLayout()

    expect(wrapper.findAll('.cpl__section').length).toBe(MATERIALS.length)
    expect(wrapper.findAll('.cpl__heading').length).toBe(
      SECTIONS.length - MATERIALS.length
    )

    // Материал — это элемент управления, поэтому кнопка.
    expect(wrapper.find('.cpl__section').element.tagName).toBe('BUTTON')
    // Тема и раздел — подписи структуры, не элементы управления.
    expect(wrapper.find('.cpl__heading').element.tagName).toBe('DIV')

    wrapper.unmount()
  })

  it('сохраняет исходный порядок узлов', () => {
    const wrapper = mountLayout()

    // Регресс: две отдельные петли v-for собирали сначала все материалы,
    // потом все заголовки и разрывали структуру курса.
    const order = [...wrapper.findAll('.cpl__heading, .cpl__section')].map((el) =>
      el.find('.cpl__heading-text, .cpl__section-text').text().trim()
    )

    expect(order).toEqual(SECTIONS.map((section) => section.title))

    wrapper.unmount()
  })

  it('темы и разделы не кликабельны и не выбираются', async () => {
    const wrapper = mountLayout()

    const heading = wrapper.findAll('.cpl__heading')[0]
    await heading.trigger('click')

    expect(wrapper.emitted('select-section')).toBeUndefined()

    wrapper.unmount()
  })

  it('отмечает прочитанные, начатые и не начатые материалы', () => {
    const wrapper = mountLayout()

    const marks = wrapper.findAll('.cpl__mark').map((m) => m.text())
    expect(marks).toEqual(['✓', '—', '—'])

    wrapper.unmount()
  })

  it('даёт подпись отметке, а не только символ', () => {
    const wrapper = mountLayout()

    const done = wrapper.findAll('.cpl__mark')[0]
    expect(done.attributes('aria-label')).toBe('Раздел пройден')

    const todo = wrapper.findAll('.cpl__mark')[1]
    expect(todo.attributes('aria-label')).toBe('Раздел не начат')

    wrapper.unmount()
  })

  it('подсвечивает активный материал и помечает его текущим', () => {
    const wrapper = mountLayout({ activeId: 4 })

    const active = wrapper
      .findAll('.cpl__section')
      .find((s) => s.classes().includes('cpl__section--active'))
    expect(active.text()).toContain('Устройство самолёта')
    expect(active.attributes('aria-current')).toBe('page')

    wrapper.unmount()
  })

  it('отступает материал, а не уровнем узла', () => {
    const wrapper = mountLayout()

    // Отступ задаётся один раз: материал всегда вложен в раздел,
    // поэтому уровень в разметке больше не читается.
    for (const section of wrapper.findAll('.cpl__section')) {
      expect(section.classes()).toContain('cpl__section--material')
    }

    wrapper.unmount()
  })

  it('сообщает о выборе материала и не меняет состояние сам', async () => {
    const wrapper = mountLayout()

    await wrapper.findAll('.cpl__section')[2].trigger('click')

    const emitted = wrapper.emitted('select-section')
    expect(emitted).toHaveLength(1)
    expect(emitted[0][0].title).toBe('Двигатель')

    wrapper.unmount()
  })

  it('показывает заглушку, когда содержание пустое', () => {
    const wrapper = mountLayout({ sections: [], progress: 0 })

    expect(wrapper.find('.cpl__empty').exists()).toBe(true)
    expect(wrapper.findAll('.cpl__section').length).toBe(0)

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: свёрнутая панель (рельс)', () => {
  it('оставляет колонку отметок и убирает всё, что в узкий рельс не влезает', async () => {
    const wrapper = mountLayout({ sidebarOpen: false })

    // Материалы остаются: в рельсе это и есть колонка иконок, по ней
    // видно прогресс и можно перейти к материалу.
    expect(wrapper.findAll('.cpl__section').length).toBe(MATERIALS.length)

    // Регресс: слот sidebar-extra (кнопки «Форум», инструменты, поиск)
    // раньше НЕ скрывался и переполнял колонку 44px — иконки вылезали
    // за край. Плюс скрывались отметки, хотя рельс для них и сделан.
    const css = readFileSync(
      'resources/js/components/ui/CoursePlayerLayout.vue',
      'utf8'
    )
    const rail = css.slice(css.indexOf('.cpl__sidebar--hidden {'))
    expect(rail).toContain('.cm-tree')
    expect(rail).toContain('.cpl__notes')
    expect(rail).toContain('.cpl__heading')
    // Подпись в рельсе не помещается — она и не нужна, отметка остаётся.
    expect(rail).toContain('.cpl__section-text')

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: переходы между уроками', () => {
  it('включает «назад», но не «вперёд» на последнем материале', () => {
    // Последний МАТЕРИАЛ (id 5), а не последний узел: заголовок не
    // участвует в переходах, иначе «вперёд» упирался бы в него.
    const wrapper = mountLayout({ activeId: 5 })

    const [prev, next] = wrapper.findAll('.cpl__pager-btn')
    expect(prev.attributes('disabled')).toBeUndefined()
    expect(next.attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })

  it('включает «вперёд» на первом разделе', () => {
    const wrapper = mountLayout({ activeId: 3 })

    const [prev, next] = wrapper.findAll('.cpl__pager-btn')
    expect(prev.attributes('disabled')).toBeDefined()
    expect(next.attributes('disabled')).toBeUndefined()

    wrapper.unmount()
  })

  it('скрывает переходы, когда активен единственный раздел', () => {
    const wrapper = mountLayout({
      sections: [{ id: 1, title: 'Единственный', state: 'done', level: 3, isMaterial: true }],
    })

    expect(wrapper.find('.cpl__pager').exists()).toBe(false)

    wrapper.unmount()
  })

  it('шлёт события prev/next', async () => {
    const wrapper = mountLayout({ activeId: 4 })

    const [prev, next] = wrapper.findAll('.cpl__pager-btn')
    await prev.trigger('click')
    await next.trigger('click')

    expect(wrapper.emitted('prev')).toHaveLength(1)
    expect(wrapper.emitted('next')).toHaveLength(1)

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: полноэкранный режим', () => {
  it('переключается по кнопке и отражается в aria-pressed', async () => {
    const wrapper = mountLayout()

    const button = wrapper.find('.cpl__bar-tools .cpl__icon-btn')
    expect(button.attributes('aria-pressed')).toBe('false')

    await button.trigger('click')

    expect(wrapper.emitted('toggle-fullscreen')).toHaveLength(1)
    // Пока родитель не подтвердил — на экране ещё не полный.
    expect(wrapper.classes()).not.toContain('cpl--fullscreen')

    wrapper.unmount()
  })

  it('показывает полноэкранное состояние и прогресс, когда включён', () => {
    const wrapper = mountLayout({ isFullscreen: true, progress: 0 })

    expect(wrapper.classes()).toContain('cpl--fullscreen')
    // В полном экране панель всегда на месте, даже если прогресс 0.
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: боковая панель', () => {
  it('сворачивается по событию, а не сама', async () => {
    const wrapper = mountLayout()

    await wrapper.find('.cpl__sidebar-head .cpl__icon-btn').trigger('click')

    expect(wrapper.emitted('toggle-sidebar')).toHaveLength(1)

    wrapper.unmount()
  })

  it('скрывает заголовки разделов в свёрнутом виде', () => {
    const wrapper = mountLayout({ sidebarOpen: false })

    expect(wrapper.find('.cpl__sidebar').classes()).toContain('cpl__sidebar--hidden')

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: заметки студента', () => {
  it('сохраняет заметки в хранилище по ключу курса', () => {
    const wrapper = mountLayout({ activeId: 42 })

    wrapper.vm.notesText = 'Моя заметка по курсу'
    wrapper.vm.saveNotes()

    expect(localStorage.getItem('course-notes-42')).toBe('Моя заметка по курсу')

    wrapper.unmount()
  })

  it('перечитывает заметки при смене курса', async () => {
    localStorage.setItem('course-notes-42', 'Заметки курса 42')
    localStorage.setItem('course-notes-99', 'Заметки курса 99')

    const wrapper = mountLayout({ activeId: 42 })
    expect(wrapper.vm.notesText).toBe('Заметки курса 42')

    await wrapper.setProps({ activeId: 99 })
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.notesText).toBe('Заметки курса 99')

    wrapper.unmount()
  })

  it('очищает заметки при пустом вводе', () => {
    localStorage.setItem('course-notes-42', 'Текст')

    const wrapper = mountLayout({ activeId: 42 })
    wrapper.vm.notesText = ''
    wrapper.vm.saveNotes()

    expect(localStorage.getItem('course-notes-42')).toBe('')

    wrapper.unmount()
  })

  it('не роняет при недоступном хранилище', () => {
    const original = Storage.prototype.setItem
    Storage.prototype.setItem = () => { throw new Error('quota') }

    const wrapper = mountLayout({ activeId: 42 })
    expect(() => wrapper.vm.saveNotes()).not.toThrow()

    Storage.prototype.setItem = original
    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: оформление держится на токенах', () => {
  it('не использует несуществующие CSS-переменные', () => {
    const source = readFileSync('resources/js/components/ui/CoursePlayerLayout.vue', 'utf-8')

    // --t-normal и --c-text-3 в проекте нет: анимация схлопывалась
    // в 0s, а приглушённый текст терял цвет.
    for (const ghost of ['--t-normal', '--c-text-3', '--c-radius-lg', '--c-shadow-sm']) {
      expect(source, `несуществующий токен ${ghost}`).not.toContain(ghost)
    }
  })
})
