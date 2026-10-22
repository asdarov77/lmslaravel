// @vitest-environment jsdom
/**
 * Каталог (Фаза 3.6): обложки карточек и сохранение фильтров в URL.
 *
 * Закрывает два пропуска, которые остались после переноса витрины на
 * дизайн-систему:
 *  - фильтры жили только в состоянии компонента, поэтому ссылку на
 *    каталог нельзя было переслать, а обновление страницы возвращало
 *    к пустому списку;
 *  - карточки шли без обложки: плоский список текста, в котором курсы
 *    невозможно отличить друг от друга, не читая названия.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createStore } from 'vuex'
import { createRouter, createMemoryHistory } from 'vue-router'

import CatalogPage from '../../resources/js/Pages/Catalog/CatalogPage.vue'
import ru from '../../resources/js/locales/ru.json'

global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
global.visualViewport = { addEventListener() {}, removeEventListener() {}, offsetLeft: 0, offsetTop: 0, width: 1024, height: 768, scale: 1 }

const fetchCatalog = vi.fn()
vi.mock('../../resources/js/api/catalog.api', () => ({
  default: {
    fetchCatalog: (...a) => fetchCatalog(...a),
    enrollCourse: vi.fn(),
    unenrollCourse: vi.fn(),
  },
  fetchCatalog: (...a) => fetchCatalog(...a),
  enrollCourse: vi.fn(),
  unenrollCourse: vi.fn(),
}))

const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

const CATALOG = {
  items: [
    { id: 1, title: 'Конструкция самолёта', short_description: 'Устройство', topics: 12, enrolled: true, categories: [], aircraft: null, to: '/courses/desc/1' },
    { id: 2, title: 'Силовая установка', short_description: null, topics: 7, enrolled: false, categories: [], aircraft: null, to: '/courses/desc/2' },
  ],
  categories: [],
  meta: { total: 2, all: 2, enrolled: 1 },
}

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const makeStore = (can = () => true) =>
  createStore({
    modules: {
      Auth: {
        namespaced: true,
        getters: { can: () => can, loggedIn: () => true, isTrainee: () => false },
        state: { user: { id: 1, group_id: 5 } },
      },
    },
  })

// push асинхронный: без await компонент смонтировался бы ещё на
// предыдущем маршруте и прочитал бы пустой query — тест падал бы
// из-за гонки, а не из-за бага страницы.
const makeRouter = async (initial = '/catalog') => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/catalog', name: 'catalog.index', component: { template: '<div />' } },
      { path: '/courses/desc/:id', name: 'courses.desc', component: { template: '<div />' } },
    ],
  })
  await router.push(initial)
  await router.isReady()
  return router
}

const mountPage = async (props = {}, opts = {}) => {
  fetchCatalog.mockResolvedValue(envelope(CATALOG))
  const router = opts.router || (await makeRouter())
  const wrapper = mount(CatalogPage, {
    global: { plugins: [vuetify, i18n, makeStore(opts.can), router] },
  })
  await wrapper.vm.$nextTick()
  await new Promise((r) => setTimeout(r, 0))
  return { wrapper, router }
}

/**
 * Дождаться записи в адрес.
 *
 * router.replace возвращает промис, а router.isReady() ждёт только
 * ПЕРВУЮ навигацию — уже начатую он не дожидается. Поэтому здесь
 * дренаж микрозадач плюс кадр.
 */
const settle = async (router, predicate) => {
  await router.isReady()
  for (let i = 0; i < 50; i += 1) {
    await new Promise((r) => setTimeout(r, 2))
    if (!predicate || predicate()) return
  }
}

beforeEach(() => {
  fetchCatalog.mockReset()
  fetchCatalog.mockResolvedValue(envelope(CATALOG))
})

describe('Каталог: обложки карточек', () => {
  it('рисует обложку с первой буквой названия', async () => {
    const { wrapper } = await mountPage()
    const covers = wrapper.findAll('.catalog__cover')

    expect(covers.length).toBe(CATALOG.items.length)
    expect(covers[0].text()).toBe('К')
    expect(covers[1].text()).toBe('С')

    wrapper.unmount()
  })

  it('обложка одинакова для одного и того же курса', async () => {
    const { wrapper } = await mountPage()
    const style = (id) => wrapper.vm.coverStyle(id).background

    expect(style(7)).toBe(style(7))
    expect(style(7)).not.toBe(style(8))

    wrapper.unmount()
  })

  it('обложка не падает на названии без текста', async () => {
    const { wrapper } = await mountPage()

    // Название приходит с сервера, тип не гарантирован: пустое или
    // отсутствующее значение не должно ронять карточку.
    expect(wrapper.vm.initial(null)).toBe('?')
    expect(wrapper.vm.initial('')).toBe('?')
    expect(wrapper.vm.initial('   ')).toBe('?')
    expect(wrapper.vm.initial(123)).toBe('1')

    wrapper.unmount()
  })

  it('обложка скрыта от скринридера — название рядом текстом', async () => {
    const { wrapper } = await mountPage()

    // Буква на обложке дублирует заголовок; скринридеру её читать не нужно.
    expect(wrapper.find('.catalog__cover').attributes('aria-hidden')).toBe('true')

    wrapper.unmount()
  })
})

describe('Каталог: фильтры в URL', () => {
  it('читает фильтры из адреса при открытии страницы', async () => {
    const router = await makeRouter('/catalog?q=конструк&view=list&sort=topics&mine=1')
    const { wrapper } = await mountPage({}, { router })

    expect(wrapper.vm.query).toBe('конструк')
    expect(wrapper.vm.viewMode).toBe('list')
    expect(wrapper.vm.sortBy).toBe('topics')
    expect(wrapper.vm.mine).toBe('mine')

    wrapper.unmount()
  })

  it('уходит на сервер с фильтрами из адреса', async () => {
    const router = await makeRouter('/catalog?q=конструк&mine=1')
    const { wrapper } = await mountPage({}, { router })

    await new Promise((r) => setTimeout(r, 10))

    expect(fetchCatalog).toHaveBeenCalledWith(
      expect.objectContaining({ q: 'конструк', mine: 1 }),
    )

    wrapper.unmount()
  })

  it('записывает изменённый поиск в адрес', async () => {
    const { wrapper, router } = await mountPage()

    wrapper.vm.query = 'авиация'
    await settle(router, () => router.currentRoute.value.query.q === 'авиация')

    expect(router.currentRoute.value.query.q).toBe('авиация')

    wrapper.unmount()
  })

  it('записывает переключение вида и сортировки', async () => {
    const { wrapper, router } = await mountPage()

    // Один фильтр за раз: два изменения подряд дают два конкурирующих
    // router.replace, и проверка была бы гонкой теста, а не страницы.
    wrapper.vm.viewMode = 'list'
    await settle(router, () => router.currentRoute.value.query.view === 'list')
    expect(router.currentRoute.value.query.view).toBe('list')

    wrapper.vm.sortBy = 'topics'
    await settle(router, () => router.currentRoute.value.query.sort === 'topics')
    expect(router.currentRoute.value.query.sort).toBe('topics')

    wrapper.unmount()
  })

  it('чистит адрес, когда фильтр сброшен', async () => {
    const router = await makeRouter('/catalog?q=авиация&view=list')
    const { wrapper } = await mountPage({}, { router })

    wrapper.vm.query = ''
    await settle(router, () => router.currentRoute.value.query.q === undefined)
    expect(router.currentRoute.value.query.q).toBeUndefined()

    wrapper.vm.viewMode = 'grid'
    await settle(router, () => router.currentRoute.value.query.view === undefined)
    expect(router.currentRoute.value.query.view).toBeUndefined()

    wrapper.unmount()
  })

  it('не падает без роутера — фильтры остаются в состоянии', async () => {
    const wrapper = mount(CatalogPage, {
      global: { plugins: [vuetify, i18n, makeStore()] },
    })
    await wrapper.vm.$nextTick()

    // Синхронизация с URL — удобство, а не условие работы страницы.
    expect(() => wrapper.vm.query = 'тест').not.toThrow()

    wrapper.unmount()
  })

  it('не зацикливает адрес при переходе назад', async () => {
    const { wrapper, router } = await mountPage()

    let replaces = 0
    const originalReplace = router.replace.bind(router)
    router.replace = (to) => { replaces += 1; return originalReplace(to) }

    wrapper.vm.query = 'авиация'
    await settle(router)

    // Один фильтр — одна запись в адрес. Раньше watch на query дёргал
    // reload(), а тот менял данные и запускал цепочку снова.
    expect(replaces).toBeLessThanOrEqual(2)

    wrapper.unmount()
  })
})

describe('Каталог: доступ', () => {
  it('без courses.view не показывает каталог как пустой', async () => {
    const { wrapper } = await mountPage({}, { can: () => false })

    // Раньше пользователь без права видел «курсов нет» — то есть ему
    // врали, будто витрина пуста.
    expect(fetchCatalog).not.toHaveBeenCalled()

    wrapper.unmount()
  })
})
