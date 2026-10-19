// @vitest-environment jsdom
/**
 * Дашборды Фазы 3.2: единые StatCard и скелетоны вместо пустого экрана.
 *
 * Что защищаем:
 *  - KPI рисуются общим StatCard (цифра главная, тренд виден), а не
 *    самодельной вёрсткой, расходившейся с дизайн-системой;
 *  - во время загрузки показывается СКЕЛЕТОН той же формы, а не пустая
 *    страница: раньше `loading` попадал в данные, но в шаблоне не
 *    использовался, и дашборд просто появлялся целиком;
 *  - после загрузки скелетоны исчезают даже при ошибке запроса —
 *    иначе страница осталась бы в состоянии «загрузка» навсегда.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createStore } from 'vuex'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'

const summary = vi.hoisted(() => vi.fn())
vi.mock('../../resources/js/api/dashboard.api', () => ({
  fetchDashboardSummary: summary,
}))

const learningApi = vi.hoisted(() => ({
  fetchMyDashboard: vi.fn(),
  fetchMyLearning: vi.fn(),
  dashboard: (r) => r?.data?.data ?? {},
  planItems: () => [],
}))
vi.mock('../../resources/js/api/learning.api', () => ({ default: learningApi }))

import ManagerDashboard from '../../resources/js/Pages/Dashboard/ManagerDashboard.vue'
import TraineeDashboard from '../../resources/js/Pages/Dashboard/TraineeDashboard.vue'
import ru from '../../resources/js/locales/ru.json'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const mountDashboard = async (component) => {
  const store = createStore({
    modules: {
      Auth: {
        namespaced: true,
        state: () => ({ user: { fio: 'Иван' } }),
        getters: { can: () => () => true },
      },
    },
  })
  const router = createRouter({
    history: createMemoryHistory(),
    // Дашборд обучаемого ссылается на маршруты учебного плана: без них
    // useLink бросает «No match for {name: learning.plan}» прямо в setup.
    routes: [
      { path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } },
      { path: '/plan/:idEdit?', name: 'learning.plan', component: { template: '<div/>' } },
      { path: '/course/:idEdit?', name: 'courses.itemmani', component: { template: '<div/>' } },
      { path: '/questions', name: 'questions', component: { template: '<div/>' } },
    ],
  })
  // Первичный переход ДО монтирования: isReady() ждёт стартовую
  // навигацию, а она начинается только когда роутер установлен в
  // приложение — без push() промис не резолвится и тест зависает.
  await router.push('/dashboard')
  await router.isReady()

  const wrapper = mount(component, {
    global: {
      plugins: [vuetify, i18n, store, router],
      stubs: {
        VProgressCircular: { name: 'VProgressCircular', template: '<span class="stub-spinner" />' },
        OnboardingTour: { name: 'OnboardingTour', props: ['steps'], template: '<div />' },
      },
    },
  })

  return { wrapper, store }
}

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

beforeEach(() => {
  vi.clearAllMocks()
  learningApi.fetchMyDashboard.mockResolvedValue(envelope({
    courses: { distinct: 3, active: 2 },
    progress: { percent: 50, lessons_completed: 5, lessons_total: 10 },
    upcoming: [],
  }))
  learningApi.fetchMyLearning.mockResolvedValue(envelope([]))
})

describe('ManagerDashboard', () => {
  it('показывает скелетоны, пока считаются показатели', async () => {
    // Ответ не отдаём — страница обязана остаться в состоянии загрузки.
    summary.mockReturnValue(new Promise(() => {}))

    const { wrapper } = await mountDashboard(ManagerDashboard)
    await flushPromises()

    expect(wrapper.vm.loading).toBe(true)
    expect(wrapper.find('.u-skeleton').exists()).toBe(true)
    expect(wrapper.find('[data-test="dash-stats"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('после загрузки показывает KPI карточками StatCard', async () => {
    summary.mockResolvedValue(envelope({
      stats: [
        { key: 'students', value: 42, label_key: 'manager.statStudents', icon: 'mdi-account-group', tone: 'success' },
        { key: 'avg', value: 74, suffix: '%', label_key: 'manager.statAverage', icon: 'mdi-percent', tone: 'accent' },
      ],
    }))

    const { wrapper } = await mountDashboard(ManagerDashboard)
    await flushPromises()

    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.find('.u-skeleton').exists()).toBe(false)

    const cards = wrapper.findAll('.u-stat')
    expect(cards.length).toBe(2)
    expect(cards[0].text()).toContain('42')
    expect(cards[1].text()).toContain('%')

    wrapper.unmount()
  })

  it('при ошибке загрузки скелетоны исчезают', async () => {
    // Иначе страница навсегда осталась бы в состоянии «загружаем».
    summary.mockRejectedValue(new Error('boom'))

    const { wrapper } = await mountDashboard(ManagerDashboard)
    await flushPromises()

    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.find('.u-skeleton').exists()).toBe(false)

    wrapper.unmount()
  })
})

describe('TraineeDashboard', () => {
  it('показывает скелетоны во время загрузки и StatCard после', async () => {
    let resolveDash
    learningApi.fetchMyDashboard.mockReturnValue(new Promise((r) => { resolveDash = r }))

    const { wrapper } = await mountDashboard(TraineeDashboard)
    await flushPromises()

    expect(wrapper.find('.u-skeleton').exists()).toBe(true)

    resolveDash(envelope({
      courses: { distinct: 3, active: 2 },
      progress: { percent: 50, lessons_completed: 5, lessons_total: 10 },
    }))
    await flushPromises()

    expect(wrapper.find('.u-skeleton').exists()).toBe(false)
    expect(wrapper.find('[data-test="dash-stats"]').exists()).toBe(true)
    expect(wrapper.findAll('.u-stat').length).toBeGreaterThan(0)

    wrapper.unmount()
  })

  it('кольцо прогресса рисуется общим компонентом', async () => {
    const { wrapper } = await mountDashboard(TraineeDashboard)
    await flushPromises()

    const ring = wrapper.find('[data-test="dash-progress-ring"]')
    expect(ring.exists()).toBe(true)
    expect(ring.find('.u-ring__value').text()).toContain('%')

    wrapper.unmount()
  })

  it('сбрасывает загрузку даже при отказе сервера', async () => {
    learningApi.fetchMyDashboard.mockRejectedValue({ response: { status: 500 } })

    const { wrapper } = await mountDashboard(TraineeDashboard)
    await flushPromises()

    expect(wrapper.vm.loading).toBe(false)

    wrapper.unmount()
  })
})

/*
 * Лента дедлайнов (Фаза 3.2).
 *
 * Раньше это был список текста: у курса не было ссылки, не показывалась
 * сама дата и не было видно, откуда взят срок — отдельный дедлайн или
 * конец периода занятий. Для обучаемого это разные вещи: период может
 * длиться месяц, а сдать нужно к конкретной дате.
 */
describe('TraineeDashboard: лента дедлайнов', () => {
  const withDeadlines = async (upcoming) => {
    learningApi.fetchMyDashboard.mockResolvedValue(envelope({
      courses: { distinct: 2, active: 1 },
      progress: { percent: 40, lessons_completed: 4, lessons_total: 10 },
      upcoming,
    }))

    return mountDashboard(TraineeDashboard)
  }

  it('строка ведёт в курс', async () => {
    const { wrapper } = await withDeadlines([
      { course_id: 12, title: 'Безопасность полётов', due_at: '2026-10-20', source: 'deadline', days_left: 3 },
    ])
    await flushPromises()

    const link = wrapper.find('[data-test="dash-deadlines"] a')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toContain('idEdit=12')

    wrapper.unmount()
  })

  it('показывает источник срока: дедлайн или конец занятий', async () => {
    const { wrapper } = await withDeadlines([
      { course_id: 1, title: 'Курс с дедлайном', due_at: '2026-10-18', source: 'deadline', days_left: 1 },
      { course_id: 2, title: 'Курс без дедлайна', due_at: '2026-10-30', source: 'study_to', days_left: 8 },
    ])
    await flushPromises()

    const text = wrapper.find('[data-test="dash-deadlines"]').text()
    expect(text).toContain('дедлайн')
    expect(text).toContain('конец занятий')

    wrapper.unmount()
  })

  it('подсвечивает срочное и не показывает пустую ленту как ошибку', async () => {
    const { wrapper } = await withDeadlines([])
    await flushPromises()

    // Пустая лента — валидное состояние (нет группы или нет сроков),
    // а не поломка: текст-заглушка вместо молчания.
    expect(wrapper.find('[data-test="dash-deadlines"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('нет')

    wrapper.unmount()
  })

  it('готовит дату без «Invalid Date» для мусорного значения', async () => {
    const { wrapper } = await withDeadlines([
      { course_id: 3, title: 'Курс', due_at: 'не-дата', source: 'deadline', days_left: 2 },
    ])
    await flushPromises()

    expect(wrapper.find('[data-test="dash-deadlines"]').text()).not.toContain('Invalid Date')

    wrapper.unmount()
  })
})
