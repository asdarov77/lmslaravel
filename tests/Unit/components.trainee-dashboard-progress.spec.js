// @vitest-environment jsdom
/**
 * Дашборд обучаемого: «продолжить обучение» и процент по урокам.
 *
 * Проверяется главное свойство нового прогресса: кнопка обещает
 * «продолжить с последнего места» только когда сервер действительно
 * знает, где человек остановился. Раньше процент считался по факту
 * открытия материалов, и один урок из двадцати выглядел как 100%.
 */
import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createStore } from 'vuex'

/**
 * Vuetify-компоненты вкладки прогресса измеряют себя через
 * ResizeObserver, которого нет в jsdom. Без заглушки падает любой
 * тест дашборда — не из-за логики компонента.
 */
globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

import TraineeDashboard from '../../resources/js/Pages/Dashboard/TraineeDashboard.vue'
import ru from '../../resources/js/locales/ru.json'

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

/**
 * Минимальная сводка дашборда.
 *
 * Меняется только продолжение и прогресс — остальные блоки нужны
 * лишь чтобы компонент отрендерился без ошибок.
 */
const summaryWith = (continueItem, progress = { percent: 65, lessons_completed: 13, lessons_total: 20 }) => ({
  courses: { total: 3, distinct: 3, active: 1, completed: 0, started: 1 },
  progress,
  continue: continueItem,
  upcoming: [],
  exams: { attempts: 0, avg_result: null, passed: 0, assigned: 0, available: 0 },
})

/**
 * Настоящий store, а не мок: компонент берёт данные через mapState,
 * и подмена $store.getters молча ломала вычисляемые свойства.
 */
const mountDashboard = (summary) => {
  const store = createStore({
    modules: {
      Auth: {
        namespaced: true,
        state: () => ({ user: { fio: 'Иван Иванов', name: 'Иван Иванов' } }),
        getters: { can: () => () => true },
      },
      // Имя модуля совпадает с тем, что ждёт компонент: сводка лежит
      // в его собственном data, а не в Vuex.
      TraineeDashboard: {
        namespaced: true,
        state: () => ({}),
      },
    },
  })

  return mount(TraineeDashboard, {
    data() {
      return { summary }
    },
    global: {
      plugins: [vuetify, i18n, store],
      mocks: { $route: { query: {} } },
      stubs: { RouterLink: { template: '<a><slot /></a>' } },
    },
  })
}

describe('TraineeDashboard — продолжить обучение', () => {
  it('показывает точный процент по урокам', () => {
    const wrapper = mountDashboard(
      summaryWith({
        course_id: 7,
        title: 'Безопасность полётов',
        status: 'active',
        started: true,
        due_at: '2026-11-01',
        percent: 65,
        resume_lesson_id: 42,
        resume_file: '2.2 Разбор.html',
      })
    )

    expect(wrapper.find('[data-test="dash-continue"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('65%')
    expect(wrapper.text()).toContain('13')
    expect(wrapper.text()).toContain('20')
  })

  it('подсказывает точку возврата, когда сервер её знает', () => {
    const wrapper = mountDashboard(
      summaryWith({
        course_id: 7,
        title: 'Безопасность полётов',
        status: 'active',
        started: true,
        percent: 30,
        resume_lesson_id: 42,
        resume_file: '2.2 Разбор.html',
      })
    )

    expect(wrapper.text()).toContain('2.2 Разбор.html')
    expect(wrapper.find('[data-test="dash-continue-go"]').exists()).toBe(true)
  })

  it('не обещает продолжение с последнего места, если точки возврата нет', () => {
    const wrapper = mountDashboard(
      summaryWith({
        course_id: 7,
        title: 'Безопасность полётов',
        status: 'active',
        started: false,
        percent: 0,
        resume_lesson_id: null,
        resume_file: null,
      })
    )

    // Обещание «продолжить с последнего места» без точки возврата —
    // это ровно то расхождение, ради которого тест и написан.
    expect(wrapper.text()).not.toContain('2.2 Разбор.html')
    expect(wrapper.text()).toContain('0%')
  })

  it('скрывает карточку, когда продолжать нечего', () => {
    const wrapper = mountDashboard(summaryWith(null, { percent: 100, lessons_completed: 20, lessons_total: 20 }))

    expect(wrapper.find('[data-test="dash-continue"]').exists()).toBe(false)
  })
})