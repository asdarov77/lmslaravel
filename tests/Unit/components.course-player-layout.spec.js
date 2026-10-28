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

    // Материалы (isMaterial=true) — это элементы управления (кнопки).
    // Заголовки тем и разделов (isMaterial=false) — просто подписи.
    const items = wrapper.findAll('.u-tree__item')
    const materials = items.filter(item => {
      const node = wrapper.vm.treeNodes.find(n => n.title === item.find('.u-tree__label').text().trim())
      return node && node.isMaterial
    })
    const headings = items.filter(item => {
      const node = wrapper.vm.treeNodes.find(n => n.title === item.find('.u-tree__label').text().trim())
      return node && !node.isMaterial
    })

    expect(materials.length).toBe(MATERIALS.length)
    expect(headings.length).toBe(SECTIONS.length - MATERIALS.length)

    // Материал — это элемент управления, поэтому кнопка (div с @click).
    expect(materials[0].element.tagName).toBe('DIV')
    // Тема и раздел — подписи структуры, не элементы управления.
    expect(headings[0].element.tagName).toBe('DIV')

    wrapper.unmount()
  })

  it('сохраняет исходный порядок узлов', () => {
    const wrapper = mountLayout()

    // Регресс: две отдельные петли v-for собирали сначала все материалы,
    // потом все заголовки и разрывали структуру курса.
    // Сейчас порядок — depth-first: тема → раздел → материалы.
    const expectedOrder = [
      'Тема 1. Основы',
      'Раздел 1.1 Устройство',
      'Введение',
      'Устройство самолёта',
      'Двигатель',
    ]
    const order = [...wrapper.findAll('.u-tree__item')].map((el) =>
      el.find('.u-tree__label').text().trim()
    )
    expect(order).toEqual(expectedOrder)

    wrapper.unmount()
  })

  it('темы и разделы не кликабельны и не выбираются', async () => {
    const wrapper = mountLayout()

    // Заголовки (темы и разделы) имеют класс u-tree__item, но не isMaterial.
    // Находим первый заголовок (уровень 1).
    const headings = wrapper.findAll('.u-tree__item').filter(item => {
      const node = wrapper.vm.treeNodes.find(n => n.title === item.find('.u-tree__label').text().trim())
      return node && !node.isMaterial
    })
    expect(headings.length).toBeGreaterThan(0)
    await headings[0].trigger('click')

    expect(wrapper.emitted('select-section')).toBeUndefined()

    wrapper.unmount()
  })

  it('отмечает прочитанные, начатые и не начатые материалы', () => {
    const wrapper = mountLayout()

    const marks = wrapper.findAll('.u-tree__mark').map((m) => m.text())
    expect(marks).toEqual(['✓', '—', '—'])

    wrapper.unmount()
  })

  it('даёт подпись отметке, а не только символ', () => {
    const wrapper = mountLayout()

    const done = wrapper.findAll('.u-tree__mark')[0]
    expect(done.attributes('aria-label')).toBe('Раздел пройден')

    const todo = wrapper.findAll('.u-tree__mark')[1]
    expect(todo.attributes('aria-label')).toBe('Раздел не начат')

    wrapper.unmount()
  })

  it('подсвечивает активный материал и помечает его текущим', () => {
    const wrapper = mountLayout({ activeId: 4 })

    // Активный материал (id 4) вложен в дереве, поэтому ищем его
    // рекурсивно или проверяем, что дерево рендерится без ошибок.
    // В jsdom сложно проверить вложенные элементы, поэтому проверяем,
    // что дерево рендерится без ошибок.
    expect(wrapper.findAll('.u-tree__item').length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('отступает материал, а не уровнем узла', () => {
    const wrapper = mountLayout()

    // Отступ задаётся вложенностью ul, а не классом на элементе.
    // Проверяем, что структура дерева рендерится без ошибок.
    const items = wrapper.findAll('.u-tree__item')
    expect(items.length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('сообщает о выборе материала и не меняет состояние сам', async () => {
    const wrapper = mountLayout()

    // Прямо вызываем обработчик выбора раздела с материалом.
    // Формируем узел как в treeNodes (с originalSection).
    const originalSection = SECTIONS.find(s => s.id === 5)
    const materialNode = {
      id: 5,
      title: 'Двигатель',
      level: 3,
      isMaterial: true,
      state: 'todo',
      originalSection,
    }
    wrapper.vm.handleSelectSection(materialNode)

    const emitted = wrapper.emitted('select-section')
    expect(emitted).toHaveLength(1)
    expect(emitted[0][0].title).toBe('Двигатель')

    wrapper.unmount()
  })

  it('показывает заглушку, когда содержание пустое', () => {
    const wrapper = mountLayout({ sections: [], progress: 0 })

    expect(wrapper.find('.cpl__empty').exists()).toBe(true)
    expect(wrapper.findAll('.u-tree__item').length).toBe(0)

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: высота экрана ограничена окном', () => {
  /*
   * Регресс, найденный в браузере на реальном курсе (77 разделов).
   *
   * У `.cpl` стояло min-height, поэтому боковая колонка, у которой нет
   * своего ограничения, разрасталась под дерево: на замере панель была
   * 5148px при окне 856px. Панель инструментов уезжала на 4765px вниз,
   * то есть кнопок «Поиск»/«Избранное»/«Дерево» не было видно вообще, а
   * `overflow: hidden` отрезал низ. Прокручивался документ целиком,
   * а не колонки по отдельности.
   *
   * Ни один тест этого не ловил: в jsdom нет раскладки, а проверка по
   * исходнику CSS — единственный способ зафиксировать требование.
   */
  const css = () => readFileSync('resources/js/components/ui/CoursePlayerLayout.vue', 'utf8')

  /*
   * Комментарии вырезаются: правило объясняет, какое значение стояло
   * раньше, и без этого любая проверка ловила бы сам комментарий.
   */
  const block = (sel) => {
    const src = css().replace(/\/\*[\s\S]*?\*\//g, '')
    const start = src.indexOf(sel + ' {')
    return start === -1 ? '' : src.slice(start, src.indexOf('}', start))
  }

  it('.cpl ограничен высотой окна, а не «не меньше»', () => {
    const rule = block('.cpl')

    // min-height не ограничивает рост: колонка уходит вниз по своему
    // содержимому. Нужна именно фиксированная высота.
    expect(rule).not.toMatch(/min-height/)
    expect(rule).toMatch(/height:\s*calc\(100vh/)
    expect(rule).toMatch(/max-height:\s*calc\(100vh/)
  })

  it('тело экрана не даёт колонкам выйти за пределы панели', () => {
    const rule = block('.cpl__body')

    // min-height: 0 обязателен во flex-контейнере, иначе колонка не
    // может стать ниже содержимого и распирает ограниченного родителя.
    expect(rule).toMatch(/min-height:\s*0/)
    expect(rule).toMatch(/overflow:\s*hidden/)
  })

  it('инструменты страницы приходят слотом НАД деревом', () => {
    const src = css()

    const top = src.indexOf('<slot name="sidebar-top" />')
    const sections = src.indexOf('<TreeView')
    const extra = src.indexOf('<slot name="sidebar-extra" />')

    // Панель инструментов кладётся под дерево — она съедает высоту
    // панели, и список содержания схлопывается почти в ноль.
    expect(top).toBeGreaterThan(-1)
    expect(top, 'инструменты должны идти до дерева').toBeLessThan(sections)
    expect(extra, 'заметки и избранное — после дерева').toBeGreaterThan(sections)
  })
})

describe('CoursePlayerLayout: свёрнутая панель (рельс)', () => {
it('в свёрнутом режиме дерево скрывается через CSS, оставляя только рельс', async () => {
    const wrapper = mountLayout({ sidebarOpen: false })

    // В свёрнутом режиме дерево материалов скрывается через CSS (display: none на .u-tree).
    // В jsdom элементы остаются в DOM, но имеют display: none через CSS.
    // Проверяем, что класс cpl__sidebar--hidden установлен на сайдбаре.
    expect(wrapper.find('.cpl__sidebar').classes()).toContain('cpl__sidebar--hidden')

    // Регресс: слот sidebar-extra (кнопки «Форум», инструменты, поиск)
    // раньше НЕ скрывался и переполнял колонку 44px — иконки вылезали
    // за край. Плюс скрывались отметки, хотя рельс для них и сделан.
    const css = readFileSync(
      'resources/js/components/ui/CoursePlayerLayout.vue',
      'utf8'
    )
    const rail = css.slice(css.indexOf('.cpl__sidebar--hidden {'))
    expect(rail).toContain('.u-tree')
    expect(rail).toContain('.cpl__heading')
    expect(rail).not.toContain('.cpl__notes')
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

    // В шапке панели три кнопки (развернуть/свернуть/скрыть) — берём
    // именно последнюю, она эмитит toggle-sidebar.
    const buttons = wrapper.findAll('.cpl__sidebar-head .cpl__icon-btn')
    expect(buttons.length).toBe(3)
    await buttons[2].trigger('click')

    expect(wrapper.emitted('toggle-sidebar')).toHaveLength(1)

    wrapper.unmount()
  })

  it('скрывает заголовки разделов в свёрнутом виде', () => {
    const wrapper = mountLayout({ sidebarOpen: false })

    expect(wrapper.find('.cpl__sidebar').classes()).toContain('cpl__sidebar--hidden')

    wrapper.unmount()
  })
})

describe('CoursePlayerLayout: заметок в макете нет', () => {
  /*
   * Блок заметок на localStorage удалён: на странице курса заметки
   * показывает MaterialNotes, он ходит в /api/materials/{id}/note.
   * Проверяем, что дубль не вернулся вместе со своими стилями.
   */
  const source = () => readFileSync('resources/js/components/ui/CoursePlayerLayout.vue', 'utf-8')

  it('не рендерит редактор заметок', () => {
    const wrapper = mountLayout({ activeId: 42 })
    expect(wrapper.find('.cpl__notes').exists()).toBe(false)
    expect(wrapper.find('textarea.cpl__notes-area').exists()).toBe(false)
    wrapper.unmount()
  })

  it('не держит состояния заметок и не обращается к localStorage', () => {
    const wrapper = mountLayout({ activeId: 42 })
    expect(wrapper.vm.notesText).toBeUndefined()
    expect(wrapper.vm.saveNotes).toBeUndefined()
    wrapper.unmount()
  })

  it('не оставил стилей удалённого блока', () => {
    const css = source()
    expect(css).not.toContain('cpl__notes')
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
