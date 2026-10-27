// @vitest-environment jsdom
/**
 * RecursiveTable (Фаза 1.5.1): рекурсивные строки с раскрытием,
 * уровни через CSS-переменные, агрегатные строки, сортировка, выбор.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import RecursiveTable from '../../resources/js/components/ui/RecursiveTable.vue'
import ru from '../../resources/js/locales/ru.json'

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
  { key: 'value', title: 'Значение', sortable: true },
  { key: 'extra', title: 'Доп.', sortable: false },
]

const ROWS = [
  { id: 1, name: 'Группа А', parent_id: null, level: 1, _level: 1, value: null, extra: null },
  { id: 2, name: 'Курс 1', parent_id: 1, level: 2, _level: 2, value: '10', extra: 'info' },
  { id: 3, name: 'Студент 1', parent_id: 2, level: 3, _level: 3, value: '5', extra: 'ok' },
  { id: 4, name: 'Студент 2', parent_id: 2, level: 3, _level: 3, value: '4', extra: 'ok' },
  { id: 5, name: 'Курс 2', parent_id: 1, level: 2, _level: 2, value: '8', extra: 'info' },
  { id: 6, name: 'Студент 3', parent_id: 5, level: 3, _level: 3, value: '3', extra: 'ok' },
  { id: 7, name: 'Группа Б', parent_id: null, level: 1, _level: 1, value: null, extra: null },
  { id: 8, name: 'Курс 3', parent_id: 7, level: 2, _level: 2, value: '5', extra: 'info' },
  { id: 9, name: 'Студент 4', parent_id: 8, level: 3, _level: 3, value: '5', extra: 'ok' },
]

const mountTable = (props = {}) => {
  localStorage.clear()

  return mount(RecursiveTable, {
    props: {
      columns: COLUMNS,
      rows: ROWS,
      rowKey: (row) => row.id,
      parentKey: 'parent_id',
      treeColumnKey: 'name',
      levelKey: '_level',
      ...props,
    },
    global: { plugins: [vuetify, i18n] },
    attachTo: document.body,
  })
}

describe('RecursiveTable: построение дерева', () => {
  it('строит иерархию из плоского массива с parent_id', () => {
    const wrapper = mountTable({ defaultExpanded: true })

    const treeCells = wrapper.findAll('.u-recursive-table__tree-content')
    expect(treeCells.length).toBe(9)

    // Проверяем уровни вложенности
    const level1 = wrapper.findAll('.u-recursive-table__row--level-1')
    const level2 = wrapper.findAll('.u-recursive-table__row--level-2')
    const level3 = wrapper.findAll('.u-recursive-table__row--level-3')

    expect(level1.length).toBe(2) // Группа А, Группа Б
    expect(level2.length).toBe(3) // Курс 1, Курс 2, Курс 3
    expect(level3.length).toBe(4) // 4 студента

    wrapper.unmount()
  })

  it('показывает кнопку раскрытия только у строк с детьми', () => {
    const wrapper = mountTable({ defaultExpanded: true })

    const toggles = wrapper.findAll('.u-recursive-table__toggle')
    // У 5 строк есть дети: 2 группы + 3 курса
    expect(toggles.length).toBe(5)

    wrapper.unmount()
  })

  it('по умолчанию все строки раскрыты (defaultExpanded=true)', () => {
    const wrapper = mountTable({ defaultExpanded: true })

    // Все строки должны быть видны
    expect(wrapper.findAll('tbody tr').length).toBe(9)

    wrapper.unmount()
  })
})

describe('RecursiveTable: раскрытие/сворачивание', () => {
  it('сворачивает детей при клике на переключатель', async () => {
    const wrapper = mountTable({ defaultExpanded: true })

    // Находим переключатель для "Группа А" (первая строка)
    const firstToggle = wrapper.findAll('.u-recursive-table__toggle')[0]
    await firstToggle.trigger('click')

    // Должно скрыться 3 ребёнка (Курс 1, Студент 1, Студент 2) + 2 внука (Курс 2, Студент 3) = 5 строк
    // Остаются: Группа А, Группа Б, Курс 3, Студент 4 = 4 строки
    expect(wrapper.findAll('tbody tr').length).toBe(4)

    wrapper.unmount()
  })

  it('снова раскрывает при повторном клике', async () => {
    const wrapper = mountTable({ defaultExpanded: true })

    const firstToggle = wrapper.findAll('.u-recursive-table__toggle')[0]
    await firstToggle.trigger('click')
    await firstToggle.trigger('click')

    expect(wrapper.findAll('tbody tr').length).toBe(9)

    wrapper.unmount()
  })

  it('выдаёт событие toggle с информацией о строке', async () => {
    const wrapper = mountTable({ defaultExpanded: true })

    const firstToggle = wrapper.findAll('.u-recursive-table__toggle')[0]
    await firstToggle.trigger('click')

    const events = wrapper.emitted('toggle')
    expect(events).toBeTruthy()
    expect(events[events.length - 1][0]).toHaveProperty('row')
    expect(events[events.length - 1][0]).toHaveProperty('expanded', false)

    wrapper.unmount()
  })
})

describe('RecursiveTable: сортировка', () => {
  it('сортирует по клику по заголовку и меняет направление', async () => {
    const wrapper = mountTable({ defaultExpanded: true })

    // Вторая строка — "Курс 1" (первая — "Группа А")
    // Без selectable: td[0] = name
    const firstDataCell = () => wrapper.findAll('tbody tr')[1].findAll('td')[0].text()

    expect(firstDataCell()).toBe('Курс 1')

    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    // После сортировки по имени (asc): Группа А, Группа Б, Курс 1, Курс 2, Курс 3, Студент 1, Студент 2, Студент 3, Студент 4
    // Вторая строка станет "Группа Б"
    expect(firstDataCell()).toBe('Группа Б')

    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    // Обратная сортировка (desc): Студент 4, Студент 3, Студент 2, Студент 1, Курс 3, Курс 2, Курс 1, Группа Б, Группа А
    // Первая строка станет "Студент 4"
    const firstRowCell = () => wrapper.findAll('tbody tr')[0].findAll('td')[0].text()
    expect(firstRowCell()).toBe('Студент 4')

    // Третий клик снимает сортировку — порядок сервера возвращается.
    await wrapper.findAll('.u-table__sort-btn')[0].trigger('click')
    expect(firstDataCell()).toBe('Курс 1')

    wrapper.unmount()
  })

  it('числа сортируются численно, а не как строки', async () => {
    const wrapper = mountTable({ defaultExpanded: true, selectable: true })

    // Кликаем по кнопке сортировки для колонки "value" (вторая сортируемая колонка)
    await wrapper.findAll('.u-table__sort-btn')[1].trigger('click')

    // td[0] = checkbox, td[1] = name, td[2] = value
    const values = wrapper.findAll('tbody tr').map((tr) => tr.findAll('td')[2].text())
    // Числовые значения: 10, 8, 5, 5, 5, 4, 3, null, null, null
    // После сортировки по возрастанию: nullы в конце (—), потом 3, 4, 5, 5, 5, 8, 10
    expect(values.filter(v => v !== '—')).toEqual(['3', '4', '5', '5', '5', '8', '10'])

    wrapper.unmount()
  })

  it('несортируемая колонка не получает кнопку', () => {
    const wrapper = mountTable()

    expect(wrapper.findAll('.u-table__sort-btn').length).toBe(2)

    wrapper.unmount()
  })
})

describe('RecursiveTable: выбор строк', () => {
  it('выбирает строку и отдаёт её наружу', async () => {
    const wrapper = mountTable({ selectable: true, defaultExpanded: true })

    await wrapper.findAll('tbody input[type="checkbox"]')[1].setValue(true)

    const selected = wrapper.emitted('update:selected')?.at(-1)?.[0] ?? []
    expect(selected.length).toBe(1)
    expect(selected[0].name).toBe('Курс 1')

    wrapper.unmount()
  })

  it('панель массовых действий появляется при выборе', async () => {
    const wrapper = mountTable({ selectable: true, defaultExpanded: true })
    expect(wrapper.find('.u-recursive-table__bulk').exists()).toBe(false)

    const checkboxes = wrapper.findAll('tbody input[type="checkbox"]')
    await checkboxes[0].setValue(true)
    expect(wrapper.find('.u-recursive-table__bulk').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('RecursiveTable: агрегатные строки', () => {
  it('показывает агрегаты для раскрытых групп', async () => {
    const aggregates = (rows) => {
      const result = []
      // Находим корневые узлы (parent_id === null)
      const roots = rows.filter(r => r.parent_id === null || r.parent_id === undefined)
      roots.forEach((group) => {
        // Находим прямых детей (уровень 2)
        const children = rows.filter(r => r.parent_id === group.id)
        const values = children.map(c => Number(c.value)).filter(v => !isNaN(v))
        if (values.length) {
          result.push({
            id: `agg_${group.id}`,
            name: 'Среднее',
            parent_id: group.id,
            _level: 2,
            _hasChildren: false,
            _parent: group.id,
            avgGrade: (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2),
          })
        }
      })
      return result
    }

    const wrapper = mountTable({ defaultExpanded: true, aggregates })

    // Должны быть агрегаты для Группы А (значения 10, 8) и Группы Б (значение 5)
    const aggregateRows = wrapper.findAll('.u-recursive-table__aggregate')
    expect(aggregateRows.length).toBe(2)

    wrapper.unmount()
  })

  it('не показывает агрегаты для свёрнутых групп', async () => {
    const aggregates = (rows) => {
      const result = []
      const roots = rows.filter(r => r.parent_id === null || r.parent_id === undefined)
      roots.forEach((group) => {
        const children = rows.filter(r => r.parent_id === group.id)
        const values = children.map(c => Number(c.value)).filter(v => !isNaN(v))
        if (values.length) {
          result.push({
            id: `agg_${group.id}`,
            name: 'Среднее',
            parent_id: group.id,
            _level: 2,
            _hasChildren: false,
            _parent: group.id,
            avgGrade: (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2),
          })
        }
      })
      return result
    }

    const wrapper = mountTable({ defaultExpanded: false, aggregates })

    // Группы свёрнуты, агрегаты не должны показываться
    const aggregateRows = wrapper.findAll('.u-recursive-table__aggregate')
    expect(aggregateRows.length).toBe(0)

    wrapper.unmount()
  })
})

describe('RecursiveTable: видимость колонок', () => {
  it('кнопка настройки колонок появляется и прячет колонку', async () => {
    const wrapper = mountTable()

    const trigger = wrapper.find('[data-test="dt-columns"]')
    expect(trigger.exists()).toBe(true)

    trigger.element.dispatchEvent(new Event('click'))
    await wrapper.vm.$nextTick()

    const items = document.body.querySelectorAll('.u-datatable__column-menu .v-list-item')
    expect(items.length).toBe(COLUMNS.length + 1)

    const checkbox = items[1].querySelector('input[type="checkbox"]')
    expect(checkbox).toBeTruthy()
    checkbox.click()
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('thead th').map((th) => th.text())).not.toContain('Значение')

    wrapper.unmount()
  })

  it('скрытие переживает перезагрузку страницы', async () => {
    const wrapper = mountTable()

    wrapper.vm.toggleColumnVisibility('value')
    await wrapper.vm.$nextTick()

    expect(JSON.parse(localStorage.getItem('recursivetable-hidden-columns'))).toContain('value')

    wrapper.unmount()
  })
})

describe('RecursiveTable: липкая шапка', () => {
  it('классы липкой шапки применяются', () => {
    const wrapper = mountTable({ stickyHeader: true })

    expect(wrapper.find('.u-recursive-table__wrap--sticky').exists()).toBe(true)
    expect(wrapper.find('.u-table__head--sticky').exists()).toBe(true)

    wrapper.unmount()
  })
})

describe('RecursiveTable: виртуализация', () => {
  it('до порога рендерит все строки без spacer', () => {
    const rows = Array.from({ length: 50 }, (_, i) => ({ id: i + 1, parent_id: null, name: `Строка ${i + 1}`, value: i }))
    const wrapper = mountTable({ rows, columns: COLUMNS.filter((c) => c.key !== 'extra') })

    expect(wrapper.findAll('tbody tr').length).toBe(50)
    expect(wrapper.find('tr[aria-hidden="true"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('после порога рендерит окно, а не весь список', () => {
    const rows = Array.from({ length: 400 }, (_, i) => ({ id: i + 1, parent_id: null, name: `Строка ${i + 1}`, value: i }))
    const wrapper = mountTable({ rows, columns: COLUMNS.filter((c) => c.key !== 'extra') })

    const rendered = wrapper.findAll('tbody tr').length
    expect(rendered).toBeGreaterThan(0)
    expect(rendered).toBeLessThan(100)

    wrapper.unmount()
  })
})

describe('RecursiveTable: оформление держится на токенах', () => {
  it('не использует несуществующие CSS-переменные', () => {
    const fs = require('node:fs')
    const source = fs.readFileSync('resources/js/components/ui/RecursiveTable.vue', 'utf-8')

    for (const ghost of ['--c-radius-lg', '--c-shadow-sm', '--c-border-light', '--c-radius-sm']) {
      expect(source, `несуществующий токен ${ghost}`).not.toContain(ghost)
    }
  })
})