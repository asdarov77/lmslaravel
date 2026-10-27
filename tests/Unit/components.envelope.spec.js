// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

// Мок httpClient: отдаём РЕАЛЬНУЮ форму ответа с middleware ApiResponseEnvelope
const http = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

// Настоящий Vuex — компоненты используют mapState/mapGetters,
// а геттеры CourseModule работают с state.courses/state.categories,
// поэтому подменять mapGetters на заглушку нельзя.
import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import CourseModule from '../../resources/js/Store/modules/CourseModule'

const vuetify = createVuetify({ components, directives })

// ButtonGroup использует useI18n — нужен настоящий плагин i18n
const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  messages: {
    ru: { app: { buttons: { save: 'сохранить', cancel: 'отмена' } } },
    en: { app: { buttons: { save: 'save', cancel: 'cancel' } } },
  },
})

import Courses from '../../resources/js/Pages/Courses.vue'
import AddClass from '../../resources/js/Pages/Course/AddClass.vue'

const makeStore = () =>
  createStore({
    modules: { Course: CourseModule },
  })

let store

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

// Глобальные заглушки Vuetify-компонентов, чтобы не тянуть весь Vuetify в jsdom
// Настоящие плагины: компоненты используют mapState/mapGetters (нужен store)
// и Vuetify-компоненты (нужен vuetify). Сами Vuetify-компоненты ставим заглушками.
const global = (store) => ({
  plugins: [store, vuetify, i18n],
  stubs: {
    'v-app': { template: '<div><slot /></div>' },
    'v-form': { template: '<form><slot /></form>' },
    'v-combobox': { template: '<div><slot /></div>' },
    'v-flex': { template: '<div><slot /></div>' },
    'v-toolbar': { template: '<div><slot /></div>' },
    'v-toolbar-title': { template: '<div><slot /></div>' },
    'v-progress-linear': true,
    'v-chip': { template: '<div class="v-chip-stub"><slot /></div>' },
    'v-divider': true,
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-card-actions': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-select': true,
    'v-text-field': true,
    'v-textarea': true,
    'v-icon': true,
    'v-list': true,
    'v-list-item': true,
    'v-menu': true,
    'v-data-table': { template: '<div><slot /></div>' },
    'router-link': { template: '<a><slot /></a>' },
    'router-view': true,
    'FileUploader': true,
  },
  mocks: {
    $t: (key) => key,
    $router: { back: vi.fn(), push: vi.fn(), go: vi.fn() },
    $route: { params: {} },
  },
})

beforeEach(() => {
  vi.clearAllMocks()
  store = makeStore()
  vi.spyOn(console, 'error').mockImplementation(() => {})
  vi.spyOn(console, 'log').mockImplementation(() => {})
})

describe('Courses.vue: /api/classes не ломает v-for', () => {
  // Именно этот баг давал: Courses.vue:21 TypeError: Cannot read properties of null (reading 'id')
  it('рендерит чипы классов, когда /api/classes отдаёт конверт с массивом', async () => {
    http.get.mockResolvedValue(envelope([{ id: 1, path: 'БПЛА' }, { id: 2, path: 'КЛЕН' }]))

    const wrapper = mount(Courses, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))
    await wrapper.vm.$nextTick()

    expect(Array.isArray(wrapper.vm.tags)).toBe(true)
    expect(wrapper.vm.tags).toHaveLength(2)
    expect(wrapper.text()).toContain('БПЛА')
    expect(wrapper.text()).toContain('КЛЕН')
  })

  it('НЕ получает в tags сам конверт (объект) — иначе v-for идёт по error: null', async () => {
    http.get.mockResolvedValue(envelope([{ id: 1, path: 'БПЛА' }]))

    const wrapper = mount(Courses, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.vm.tags.success).toBeUndefined()
    expect(wrapper.vm.tags.error).toBeUndefined()
  })

  it('пустой ответ /api/classes не роняет рендер', async () => {
    http.get.mockResolvedValue(envelope([]))

    const wrapper = mount(Courses, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.tags).toEqual([])
  })

  it('data === null не превращается в конверт', async () => {
    http.get.mockResolvedValue(envelope(null))

    const wrapper = mount(Courses, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.vm.tags).toEqual([])
    expect(() => wrapper.vm.tags.map((t) => t.id)).not.toThrow()
  })
})

describe('AddClass.vue: /api/classesfs и /api/classes', () => {
  it('allTags — массив из конверта, а не объект', async () => {
    http.get.mockResolvedValue(envelope(['БПЛА', 'КЛЕН']))

    const wrapper = mount(AddClass, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.allTags).toEqual(['БПЛА', 'КЛЕН'])
    expect(Array.isArray(wrapper.vm.tags)).toBe(true)
  })

  it('пустой /api/classesfs даёт пустой массив', async () => {
    http.get.mockResolvedValue(envelope([]))

    const wrapper = mount(AddClass, { global: global(store) })
    await new Promise((r) => setTimeout(r, 10))

    expect(wrapper.vm.allTags).toEqual([])
  })
})
