// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const http = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import CourseModule from '../../resources/js/Store/modules/CourseModule'

import UpdateCategory from '../../resources/js/Pages/Category/UpdateCategory.vue'
import CategoryList from '../../resources/js/Pages/Category/CategoryList.vue'

const vuetify = createVuetify({ components, directives })
const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  messages: {
    ru: { app: { buttons: { save: 'сохранить', cancel: 'отмена' } } },
    en: { app: { buttons: { save: 'save', cancel: 'cancel' } } },
  },
})

const envelope = data => ({ data: { success: true, data, error: null, meta: null } })
const tick = () => new Promise(r => setTimeout(r, 0))

let store
let back

beforeEach(() => {
  vi.clearAllMocks()
  back = vi.fn()
  store = createStore({ modules: { Course: CourseModule } })
})

const mountPage = (component, props = {}) =>
  mount(component, {
    store,
    vuetify,
    i18n,
    propsData: props,
    global: {
      mocks: {
        $store: store,
        $route: { params: { idEdit: '1' } },
        $router: { back, push: vi.fn() },
      },
      stubs: {
        'v-text-field': { template: '<input />' },
        // ButtonGroup вызывает useI18n() в setup() — без плагина i18n,
        // установленного через app.use, монтирование падает.
        ButtonGroup: { name: 'ButtonGroup', template: '<div />' },
      },
    },
  })

// ------------------------------------- РЕГРЕСС: правка категории не сохранялась

describe('UpdateCategory: сохранение правки', () => {
  it('на сервер уходят только редактируемые поля, без алиаса name', async () => {
    // Сервер отдаёт категорию с name — это appended-алиас, уже загруженный
    // при первом GET. Раньше весь объект state.category уходил в PUT, и
    // CategoryController::normalizeName() отдавал приоритет name, из-за
    // чего отредактированный title молча затирался старым алиасом.
    store.commit('Course/SET_CATEGORY', {
      id: 1,
      title: 'Старое',
      description: 'старое описание',
      code: 'PILOT',
      aircraft_id: null,
      created_at: null,
      updated_at: '2024-01-01T00:00:00.000000Z',
      name: 'Старое',
    })

    http.put.mockResolvedValue(envelope({ id: 1, title: 'Новое', name: 'Новое' }))

    const wrapper = mountPage(UpdateCategory, { idEdit: 1 })
    // Пользователь правит только название
    store.commit('Course/SET_CATEGORY', { title: 'Новое' })
    await wrapper.vm.submitForm()
    await tick()

    expect(http.put).toHaveBeenCalledTimes(1)
    const [url, payload] = http.put.mock.calls[0]
    expect(url).toBe('/api/categories/1')
    // Ровно два поля — никаких name/id/created_at/updated_at
    expect(Object.keys(payload).sort()).toEqual(['description', 'title'])
    expect(payload.title).toBe('Новое')
    expect(payload.name).toBeUndefined()
  })

  it('после успешной правки название обновляется в сторе и уходит назад', async () => {
    store.commit('Course/SET_CATEGORY', { id: 1, title: 'Борт-инженер', description: 'd' })
    http.put.mockResolvedValue(envelope({ id: 1, title: 'Борт-инженер', name: 'Борт-инженер' }))

    const wrapper = mountPage(UpdateCategory, { idEdit: 1 })
    await wrapper.vm.submitForm()
    await tick()

    // Список на /categories обязан показать новое название без перезагрузки
    store.commit('Course/SET_ALL_CATEGORIES', [{ id: 1, title: 'Летчик' }])
    await wrapper.vm.submitForm()
    await tick()

    expect(store.state.Course.categories[0].title).toBe('Борт-инженер')
    expect(store.state.Course.category.title).toBe('Борт-инженер')
    expect(back).toHaveBeenCalled()
    expect(wrapper.vm.errors).toEqual([])
  })

  it('при ошибке валидации показывает текст и НЕ уходит со страницы', async () => {
    const err = new Error('422')
    err.response = { data: { errors: { title: ['Название обязательно'] } } }
    http.put.mockRejectedValue(err)

    const wrapper = mountPage(UpdateCategory, { idEdit: 1 })
    await wrapper.vm.submitForm()
    await tick()

    expect(wrapper.vm.errors.join(' ')).toMatch(/Название обязательно/)
    expect(back).not.toHaveBeenCalled()
  })

  it('при сетевой ошибке без деталей тоже остаёмся на странице', async () => {
    http.put.mockRejectedValue(new Error('Network Error'))

    const wrapper = mountPage(UpdateCategory, { idEdit: 1 })
    await wrapper.vm.submitForm()
    await tick()

    expect(wrapper.vm.errors.join(' ')).toMatch(/Не удалось сохранить/)
    expect(back).not.toHaveBeenCalled()
  })
})

// ------------------------------ РЕГРЕСС: /categories показывал пустую таблицу

describe('CategoryList: таблица заполнена сразу', () => {
  it('категории из стора попадают в таблицу без клика по фильтру', async () => {
    const categories = [
      { id: 1, title: 'Летчик', description: 'курсы для летчика', aircraft_id: null },
      { id: 2, title: 'Борт инженер', description: 'курсы для борт инженера', aircraft_id: null },
    ]
    http.get.mockImplementation(url => {
      if (url === '/api/categories') return Promise.resolve(envelope(categories))
      return Promise.resolve(envelope([]))
    })

    store.commit('Course/SET_ALL_CATEGORIES', categories)
    const wrapper = mountPage(CategoryList)
    await tick()

    // filtredCat инициализировался [] и заполнялся только методом filter()
    // по клику на чекбокс «борт» — при обычном заходе было «0-0 of 0»
    expect(wrapper.vm.filtredCat).toHaveLength(2)
    expect(wrapper.text()).toContain('Летчик')
    expect(wrapper.text()).toContain('Борт инженер')
  })

  it('filtredCat не остаётся массивом, если сервер вернул null', async () => {
    http.get.mockResolvedValue(envelope(null))
    store.commit('Course/SET_ALL_CATEGORIES', [])
    const wrapper = mountPage(CategoryList)
    await tick()

    expect(wrapper.vm.filtredCat).toEqual([])
  })
})
