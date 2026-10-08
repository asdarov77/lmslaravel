// @vitest-environment jsdom
/**
 * Витрина курсов: фильтры, запись, отписка.
 *
 * Закрывает то, что ломало компонент по построению:
 *  - поиск без задержки отправлял запрос на каждую букву;
 *  - после записи флажок менялся локально, хотя сервер мог ничего не
 *    создать (повторная запись возвращает changed: false) — доверять
 *    оптимистичному обновлению здесь нельзя;
 *  - управляющему курсами кнопка «Записаться» не нужна: материал ему
 *    и так доступен, а в его учебном плане курса не будет;
 *  - незаписанному нельзя показывать ссылку на материал: она ведёт в 403.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createStore } from 'vuex'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import CatalogPage from '../../resources/js/Pages/Catalog/CatalogPage.vue'
import { toastItems, clear } from '../../resources/js/composables/useToast'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  fallbackLocale: 'ru',
  messages: { ru, en },
})
/*
 * Уведомления ушли в общую очередь composables/useToast, поэтому тесты
 * смотрят её и очищают после себя: очередь переживает тест, и
 * сообщение из одного утекло бы в следующий.
 */
const shownToasts = () => toastItems.map(({ type, text }) => ({ type, text }))
const clearToasts = () => clear()


global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
global.visualViewport = { addEventListener() {}, removeEventListener() {}, offsetLeft: 0, offsetTop: 0, width: 1024, height: 768, scale: 1 }

const fetchCatalog = vi.fn()
const enrollCourse = vi.fn()
const unenrollCourse = vi.fn()
vi.mock('../../resources/js/api/catalog.api', () => ({
  default: { fetchCatalog: (...a) => fetchCatalog(...a), enrollCourse: (...a) => enrollCourse(...a), unenrollCourse: (...a) => unenrollCourse(...a) },
  fetchCatalog: (...a) => fetchCatalog(...a),
  enrollCourse: (...a) => enrollCourse(...a),
  unenrollCourse: (...a) => unenrollCourse(...a),
}))

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

const CATALOG = {
  items: [
    { id: 1, title: 'Конструкция', short_description: 'Курс про конструкцию', topics: 12, enrolled: true,
      categories: [{ id: 3, title: 'Специальность' }], aircraft: 'Ил-76', to: '/courses/desc/1' },
    { id: 2, title: 'Силовая установка', short_description: null, topics: 7, enrolled: false,
      categories: [], aircraft: null, to: '/courses/desc/2' },
  ],
  categories: [{ id: 3, title: 'Специальность' }],
  meta: { total: 2, all: 2, enrolled: 1 },
}

const mountCatalog = ({ perms = ['courses.view'], user = { group_id: 1 } } = {}) => {
  const store = createStore({
    modules: {
      Auth: {
        namespaced: true,
        getters: {
          can: () => (...list) => list.flat().some(p => perms.includes(p)),
        },
        state: { user },
      },
    },
  })
  const push = vi.fn(() => Promise.resolve())

  return mount(CatalogPage, {
    global: {
      plugins: [vuetify, i18n, store],
      mocks: { $router: { push } },
      stubs: { 'v-dialog': { template: '<div><slot /></div>' } },
    },
  })
}

const flush = async () => {
  await new Promise(r => setTimeout(r, 400))
  await Promise.resolve()
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchCatalog.mockResolvedValue(envelope(CATALOG))
  enrollCourse.mockResolvedValue(envelope({ course_id: 2, enrolled: true, changed: true }))
  unenrollCourse.mockResolvedValue(envelope({ course_id: 1, enrolled: false, changed: true }))
})

describe('CatalogPage: загрузка', () => {
  it('показывает курсы и счётчик', async () => {
    const wrapper = mountCatalog()
    await flush()

    expect(fetchCatalog).toHaveBeenCalled()
    expect(wrapper.findAll('[data-test="catalog-card"],[data-test="catalog-card-enrolled"]')).toHaveLength(2)
    expect(wrapper.find('[data-test="catalog-count"]').text()).toContain('2')
  })

  it('отмечает записанные и незаписанные по-разному', async () => {
    const wrapper = mountCatalog()
    await flush()

    expect(wrapper.findAll('[data-test="catalog-card-enrolled"]')).toHaveLength(1)
    expect(wrapper.findAll('[data-test="catalog-card"]')).toHaveLength(1)
  })

  it('не показывает ссылку на материал незаписанному', async () => {
    // Ссылка ведёт в 403: материал открыт только записанному.
    const wrapper = mountCatalog()
    await flush()

    expect(wrapper.findAll('[data-test="catalog-open"]')).toHaveLength(1)
  })

  it('показывает пустое состояние, когда каталог пуст', async () => {
    fetchCatalog.mockResolvedValue(envelope({ items: [], categories: [], meta: { total: 0, all: 0 } }))
    const wrapper = mountCatalog()
    await flush()

    expect(wrapper.text()).toContain('Курсов нет')
  })

  it('ошибка сервера показывается текстом', async () => {
    fetchCatalog.mockRejectedValue({ response: { data: { error: { message: 'Каталог недоступен' } } } })
    const wrapper = mountCatalog()
    await flush()

    expect(shownToasts().map((t) => t.text)).toContain('Каталог недоступен')
    clearToasts()
  })
})

describe('CatalogPage: фильтры', () => {
  it('поиск с задержкой: одна отправка на серию нажатий', async () => {
    const wrapper = mountCatalog()
    await flush()
    fetchCatalog.mockClear()

    wrapper.vm.query = 'ко'
    await wrapper.vm.scheduleReload()
    wrapper.vm.query = 'конс'
    await wrapper.vm.scheduleReload()
    wrapper.vm.query = 'конст'
    await wrapper.vm.scheduleReload()

    // scheduleReload возвращает void — ждём debounce сами.
    await new Promise(r => setTimeout(r, 400))

    expect(fetchCatalog).toHaveBeenCalledTimes(1)
    expect(fetchCatalog).toHaveBeenCalledWith(expect.objectContaining({ q: 'конст' }))
  })

  it('фильтр «мои курсы» уходит на сервер', async () => {
    const wrapper = mountCatalog()
    await flush()
    fetchCatalog.mockClear()

    wrapper.vm.mine = 'mine'
    await wrapper.vm.reload()

    expect(fetchCatalog).toHaveBeenCalledWith(expect.objectContaining({ mine: 1 }))
  })

  it('пустой поиск не отправляется как q=a', async () => {
    const wrapper = mountCatalog()
    await flush()
    fetchCatalog.mockClear()

    wrapper.vm.query = '  '
    await wrapper.vm.reload()

    const params = fetchCatalog.mock.calls[0][0]
    expect(params.q).toBeUndefined()
  })
})

describe('CatalogPage: запись и отписка', () => {
  it('записывает и обновляет карточку', async () => {
    const wrapper = mountCatalog()
    await flush()

    const target = wrapper.vm.items.find(i => !i.enrolled)
    await wrapper.vm.toggle(target)

    expect(enrollCourse).toHaveBeenCalledWith(2)
    expect(wrapper.vm.items.find(i => i.id === 2).enrolled).toBe(true)
    expect(shownToasts().some((t) => t.type === 'success')).toBe(true)
    clearToasts()
  })

  it('учитывает ответ сервера: повторная запись не меняет состояние наоборот', async () => {
    // Сервер вернул changed: false (запись уже была) — значит состояние
    // остаётся «записан», даже если клиент считал иначе.
    enrollCourse.mockResolvedValue(envelope({ course_id: 2, enrolled: true, changed: false }))
    const wrapper = mountCatalog()
    await flush()

    await wrapper.vm.toggle(wrapper.vm.items.find(i => !i.enrolled))

    expect(wrapper.vm.items.find(i => i.id === 2).enrolled).toBe(true)
  })

  it('при ошибке записи состояние не меняется', async () => {
    enrollCourse.mockRejectedValue({ response: { data: { error: { message: 'Нет прав' } } } })
    const wrapper = mountCatalog()
    await flush()

    await wrapper.vm.toggle(wrapper.vm.items.find(i => !i.enrolled))

    expect(wrapper.vm.items.find(i => i.id === 2).enrolled).toBe(false)
    const shown = shownToasts()
    expect(shown.some((t) => t.type === 'error')).toBe(true)
    expect(shown.map((t) => t.text)).toContain('Нет прав')
    clearToasts()
  })

  it('отписка снимает отметку', async () => {
    const wrapper = mountCatalog()
    await flush()

    await wrapper.vm.toggle(wrapper.vm.items.find(i => i.enrolled))

    expect(unenrollCourse).toHaveBeenCalledWith(1)
    expect(wrapper.vm.items.find(i => i.id === 1).enrolled).toBe(false)
  })

  it('две кнопки не нажимаются одновременно', async () => {
    const wrapper = mountCatalog()
    await flush()

    let release
    enrollCourse.mockImplementation(() => new Promise(r => (release = r)))

    const first = wrapper.vm.toggle(wrapper.vm.items.find(i => !i.enrolled))
    const second = wrapper.vm.toggle(wrapper.vm.items.find(i => i.id === 3))
    await flush()

    expect(enrollCourse).toHaveBeenCalledTimes(1)
    release?.(envelope({ course_id: 2, enrolled: true, changed: true }))
    await first
    await second
  })
})

describe('CatalogPage: кому доступна запись', () => {
  it('обучающему с courses.view кнопка есть', async () => {
    const wrapper = mountCatalog({ perms: ['courses.view'] })
    await flush()

    expect(wrapper.vm.canEnroll).toBe(true)
    expect(wrapper.findAll('[data-test="catalog-enroll"]').length).toBeGreaterThan(0)
  })

  it('управляющему курсами кнопки записи нет', async () => {
    // Он и так видит материал, а в его учебном плане курса не будет.
    const wrapper = mountCatalog({ perms: ['courses.view', 'courses.manage'] })
    await flush()

    expect(wrapper.vm.canEnroll).toBe(false)
    expect(wrapper.find('[data-test="catalog-noenroll"]').exists()).toBe(true)
  })

  it('без права courses.view витрина не запрашивается', async () => {
    const wrapper = mountCatalog({ perms: [] })
    await flush()

    expect(fetchCatalog).not.toHaveBeenCalled()
  })
})
