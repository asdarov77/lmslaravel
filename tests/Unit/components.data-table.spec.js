// @vitest-environment jsdom
/**
 * DataTable (Фаза 3.6): сортировка, sticky-шапка, выбор строк,
 * видимость колонок и мобильный карточный режим.
 *
 * Здесь закрыты реальные дефекты, найденные при чтении кода:
 *  1. `isMobile` использовался в шаблоне, но нигде не был объявлен —
 *     обращение к undefined давало false, и карточный режим на
 *     телефоне не включался НИКОГДА: таблица в 12 колонок с
 *     горизонтальной прокруткой;
 *  2. `stickyHeader` и классы липкой шапки существовали в стилях, но
 *     не применялись в разметке — заголовок не прилипал;
 *  3. toggleColumnVisibility/resetColumns были написаны, но кнопки
 *     не было — спрятать лишнюю колонку было невозможно;
 *  4. мобильные карточки ссылались на несуществующие токены
 *     (--c-radius-lg, --c-shadow-sm, --c-border-light) — то есть
 *     остались без рамки и тени.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import DataTable from '../../resources/js/components/ui/DataTable.vue'
import ru from '../../resources/js/locales/ru.json'
import { readFileSync } from 'node:fs'

// Компоненты Vuetify внутри меню (VOverlay, VCheckbox) измеряют себя
// через visualViewport и ResizeObserver — в jsdom их нет.
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

if (!globalThis.visualViewport) {
  globalThis.visualViewport = {
    width: 1024,
    height: 768,
    offsetLeft: 0,
    offsetTop: 0,
    scale: 1,
    addEventListener() {},
    removeEventListener() {},
  }
}

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const COLUMNS = [
  { key: 'name', title: 'Название', sortable: true },
  { key: 'count', title: 'Ответов', sortable: true },
  { key: 'updated', title: 'Обновлён', sortable: false },
]

const ROWS = [
  { id: 1, name: 'Второй вопрос', count: 5, updated: '2026-10-02' },
  { id: 2, name: 'Альфа', count: 2, updated: '2026-10-01' },
  { id: 3, name: 'Третий', count: 12, updated: '2026-10-03' },
]

/** Управление шириной окна для проверки мобильного режима. */
let matchMediaStub

const setMobile = (matches) => {
  if (matchMediaStub) matchMediaStub.matches = matches
}

const mountTable = (props = {}) => {
  localStorage.clear()

  return mount(DataTable, {
    props: { columns: COLUMNS, rows: ROWS, rowKey: (row) => row.id, ...props },
    global: { plugins: [vuetify, i18n] },
    attachTo: document.body,
  })
}

beforeEach(() => {
  matchMediaStub = { matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn(), addListener: vi.fn(), removeListener: vi.fn() }
  window.matchMedia = vi.fn(() => matchMediaStub)
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('DataTable: сортировка', () => {
  it('сортирует по клику по заголовку и меняет направление', async () => {
    const wrapper = mountTable()
    const firstCell = () => wrapper.findAll('tbody tr')[0].findAll('td')[0].text()

    expect(firstCell()).toBe('Второй вопрос')

    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    expect(firstCell()).toBe('Альфа')

    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    expect(firstCell()).toBe('Третий')

    // Третий клик снимает сортировку — порядок сервера возвращается.
    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    expect(firstCell()).toBe('Второй вопрос')

    wrapper.unmount()
  })

  it('числа сортируются численно, а не как строки', async () => {
    const wrapper = mountTable()
    await wrapper.findAll('.u-table__sort-btn')[1].trigger('click')

    const counts = wrapper.findAll('tbody tr').map((tr) => tr.findAll('td')[1].text())
    // Строковая сортировка дала бы 12, 2, 5 — это и была ошибка.
    expect(counts).toEqual(['2', '5', '12'])

    wrapper.unmount()
  })

  it('несортируемая колонка не получает кнопку', () => {
    const wrapper = mountTable()

    expect(wrapper.findAll('.u-table__sort-btn').length).toBe(2)

    wrapper.unmount()
  })
})

describe('DataTable: выбор строк и массовые действия', () => {
  it('выбирает строку и отдаёт её наружу', async () => {
    const wrapper = mountTable({ selectable: true })

    await wrapper.findAll('tbody input[type="checkbox"]')[1].setValue(true)

    const selected = wrapper.emitted('update:selected')?.at(-1)?.[0] ?? []
    expect(selected.map((r) => r.id)).toEqual([2])

    wrapper.unmount()
  })

  it('панель массовых действий появляется только при выборе', async () => {
    const wrapper = mountTable({ selectable: true })
    expect(wrapper.find('.u-card__bulk').exists()).toBe(false)

    await wrapper.findAll('tbody input[type="checkbox"]')[0].setValue(true)
    expect(wrapper.find('.u-card__bulk').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('DataTable: видимость колонок', () => {
  it('кнопка настройки колонок появляется и прячет колонку', async () => {
    const wrapper = mountTable()

    const trigger = wrapper.find('[data-test="dt-columns"]')
    expect(trigger.exists(), 'кнопка видимости колонок должна быть').toBe(true)

    // Открываем меню и снимаем галочку с колонки «Ответов».
    trigger.element.dispatchEvent(new Event('click'))
    await wrapper.vm.$nextTick()

    const items = document.body.querySelectorAll('.u-datatable__column-menu .v-list-item')
    expect(items.length).toBe(COLUMNS.length + 1) // +1 «показать все»

    // Кликаем по переключателю внутри строки, а не по самой строке.
    const checkbox = items[1].querySelector('input[type="checkbox"]')
    expect(checkbox, 'у строки должен быть переключатель').toBeTruthy()
    checkbox.click()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('thead th').map((th) => th.text())).not.toContain('Ответов')

    wrapper.unmount()
  })

  it('скрытие переживает перезагрузку страницы', async () => {
    const wrapper = mountTable()

    wrapper.vm.toggleColumnVisibility('count')
    await wrapper.vm.$nextTick()

    expect(JSON.parse(localStorage.getItem('datatable-hidden-columns'))).toContain('count')

    wrapper.unmount()
  })
})

describe('DataTable: липкая шапка', () => {
  it('классы липкой шапки применяются, когда включён stickyHeader', () => {
    const wrapper = mountTable({ stickyHeader: true })

    expect(wrapper.find('.u-table-wrap--sticky').exists()).toBe(true)
    expect(wrapper.find('.u-table__head--sticky').exists()).toBe(true)

    wrapper.unmount()
  })

  it('при stickyHeader=false шапка не липкая', () => {
    const wrapper = mountTable({ stickyHeader: false })

    expect(wrapper.find('.u-table-wrap--sticky').exists()).toBe(false)

    wrapper.unmount()
  })
})

describe('DataTable: мобильный режим', () => {
  it('isMobile вычисляется, а не остаётся undefined', () => {
    const wrapper = mountTable()

    // Регресс: обращение к необъявленному isMobile давало undefined,
    // и `v-if="mobileCards && isMobile"` никогда не срабатывало.
    expect(wrapper.vm.isMobile).toBe(false)

    wrapper.unmount()
  })

  it('на узком экране показывает карточки вместо таблицы', async () => {
    setMobile(true)
    // matchMedia вызывается при монтировании, поэтому пересоздаём
    // компонент уже с «мобильным» значением.
    const wrapper = mount(DataTable, {
      props: { columns: COLUMNS, rows: ROWS, rowKey: (row) => row.id, mobileCards: true },
      global: { plugins: [vuetify, i18n] },
    })

    expect(wrapper.vm.isMobile).toBe(true)
    expect(wrapper.find('.u-table__mobile-cards').exists()).toBe(true)
    expect(wrapper.findAll('.u-table__mobile-card').length).toBe(ROWS.length)

    wrapper.unmount()
  })

  it('на широком экране остаётся таблица', () => {
    const wrapper = mountTable()

    expect(wrapper.find('.u-table__mobile-cards').exists()).toBe(false)
    expect(wrapper.find('table.u-table').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('DataTable: оформление держится на токенах', () => {
  it('не использует несуществующие CSS-переменные', () => {
    const source = readFileSync('resources/js/components/ui/DataTable.vue', 'utf-8')

    // Раньше мобильные карточки ссылались на --c-radius-lg,
    // --c-shadow-sm и --c-border-light, которых в tokens.css нет:
    // карточки оставались без рамки и тени.
    for (const ghost of ['--c-radius-lg', '--c-shadow-sm', '--c-border-light', '--c-radius-sm']) {
      expect(source, `несуществующий токен ${ghost}`).not.toContain(ghost)
    }
  })
})
