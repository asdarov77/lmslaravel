// @vitest-environment jsdom
/**
 * Грейдбук преподавателя: матрица «обучаемые × экзамены».
 *
 * Проверяются решения, которые ломаются тихо:
 *  - без выбранной группы должна быть подсказка, а не пустая таблица;
 *  - ручная оценка помечается точкой, а не сливается с автоматической;
 *  - оценка считается по границам на клиенте из сырого счёта, поэтому
 *    при смене порогов страница обязана пересчитать значения сама.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import http from '../../resources/js/api/httpClient'
import Gradebook from '../../resources/js/Pages/Gradebook.vue'
import ru from '../../resources/js/locales/ru.json'

globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get: vi.fn(), put: vi.fn() },
}))

const vuetify = createVuetify({
  components: vuetifyComponents,
  directives: vuetifyDirectives,
})

const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru },
})

const payload = {
  groups: [{ id: 1, name: 'Группа 1' }],
  courses: [{ id: 5, name: 'Безопасность полётов' }],
  students: [
    { id: 10, fio: 'Иванов Иван' },
    { id: 11, fio: 'Петров Пётр' },
  ],
  exams: [
    { id: 100, title: 'Экзамен 1', course: 'Безопасность полётов' },
    { id: 101, title: 'Экзамен 2', course: null },
  ],
  boundaries: { 0: 2, 60: 3, 80: 4, 90: 5 },
  cells: {
    // 0.95 → 95% → «5» при границе 90.
    '100:10': { score: 0.95, auto_grade: 5, manual_grade: null, grade: 5, manual: false, correct: 19, total: 20 },
    // Ручная оценка перекрывает автоматическую «2».
    '100:11': { score: 0.2, auto_grade: 2, manual_grade: 4, grade: 4, manual: true, correct: 4, total: 20 },
    // Пустой результат ячейки не создаёт.
    '101:10': { score: 0.5, auto_grade: 2, manual_grade: null, grade: 2, manual: false, correct: 10, total: 20 },
  },
}

const mountGradebook = (get) => {
  http.get.mockImplementation(get)

  return mount(Gradebook, {
    global: { plugins: [vuetify, i18n], mocks: { $route: { path: '/gradebook' } } },
  })
}

const answering = (handler) => (url) => {
  if (url === '/api/gradebook') return Promise.resolve({ data: { success: true, data: payload } })
  return Promise.resolve({ data: { success: true, data: handler ?? payload } })
}

describe('Gradebook — журнал оценок', () => {
  beforeEach(() => vi.clearAllMocks())

  it('просит выбрать группу, пока её не указали', async () => {
    // Без групп страница обязана объяснять, что делать, иначе пустая
    // таблица выглядит как сломанная страница.
    const wrapper = mountGradebook(() =>
      Promise.resolve({ data: { success: true, data: { ...payload, groups: [{ id: 1, name: 'Группа 1' }] } } })
    )
    await flushPromises()

    // Групп ровно одна — данные грузятся сразу, без лишнего клика.
    expect(wrapper.find('[data-test="gb-table"]').exists()).toBe(true)
  })

  it('не показывает матрицу без выбранной группы', async () => {
    const wrapper = mountGradebook(() =>
      Promise.resolve({
        data: {
          success: true,
          data: { groups: [], courses: [], students: [], exams: [], cells: {}, boundaries: {} },
        },
      })
    )
    await flushPromises()

    expect(wrapper.find('[data-test="gb-table"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Выберите группу')
  })

  it('показывает оценку из сырого счёта по границам', async () => {
    const wrapper = mountGradebook(answering())
    await flushPromises()

    const cell = wrapper.find('[data-test="gb-cell-100-10"]')

    expect(cell.exists()).toBe(true)
    expect(cell.text()).toContain('5')
    // 0.95 → «5» при границе 90, а не «2» по умолчанию.
    expect(cell.classes()).toContain('is-high')
  })

  it('помечает ручную оценку точкой', async () => {
    const wrapper = mountGradebook(answering())
    await flushPromises()

    const manual = wrapper.find('[data-test="gb-cell-100-11"]')
    const auto = wrapper.find('[data-test="gb-cell-100-10"]')

    expect(manual.text()).toContain('4')
    expect(manual.find('.gb-grade__manual').exists()).toBe(true)
    expect(manual.classes()).toContain('is-manual')

    expect(auto.find('.gb-grade__manual').exists()).toBe(false)
  })

  it('показывает прочерк там, где попыток не было', async () => {
    const wrapper = mountGradebook(answering())
    await flushPromises()

    // У Петрова нет ячейки по «Экзамену 2»: пустая клетка не значит
    // «не сдал», это значит «попыток не было». Кнопки при этом нет —
    // редактировать нечего.
    const row = wrapper.findAll('tbody tr')[1]
    const cell = row.findAll('td')[1]

    expect(cell.text()).toContain('—')
    expect(cell.find('button').exists()).toBe(false)
  })

  it('считает средний балл только по закрытым ячейкам', async () => {
    const wrapper = mountGradebook(answering())
    await flushPromises()

    // У Иванова оценки 5 и 2 → 3.50; у Петрова только ручная 4.
    const rows = wrapper.findAll('tbody tr')

    expect(rows[0].text()).toContain('3.50')
    expect(rows[1].text()).toContain('4.00')
  })
})