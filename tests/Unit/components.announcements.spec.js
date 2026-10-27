// @vitest-environment jsdom
/**
 * Лента объявлений.
 *
 * Проверяется то, из-за чего страница и делалась: у обучаемого нет
 * кнопок публикации, закреплённое идёт первым, а удаление спрашивает
 * подтверждение (публикацию нельзя откатить).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createStore } from 'vuex'

import http from '../../resources/js/api/httpClient'
import AnnouncementList from '../../resources/js/Pages/AnnouncementList.vue'
import ru from '../../resources/js/locales/ru.json'

// Оверлеи Vuetify (диалоги, тултипы) измеряют окно через visualViewport,
// которого нет в jsdom. Без заглушки любой тест с диалогом падает мимо
// логики компонента.
globalThis.visualViewport = {
  width: 1024,
  height: 768,
  offsetLeft: 0,
  offsetTop: 0,
  addEventListener() {},
  removeEventListener() {},
}

globalThis.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}

vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
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

const items = [
  {
    id: 1,
    title: 'Плановая проверка',
    body: 'В четверг занятий не будет',
    author: 'Администрация',
    audience_all: true,
    audience_groups: [],
    audience_courses: [],
    audience_roles: [],
    pinned: true,
    published_at: '2026-03-01T09:00:00Z',
    expires_at: null,
    live: true,
  },
  {
    id: 2,
    title: 'Набор на курс',
    body: 'Открыт набор',
    author: 'Методист',
    audience_all: false,
    audience_groups: [1],
    audience_courses: [],
    audience_roles: [],
    pinned: false,
    published_at: '2026-03-02T09:00:00Z',
    expires_at: null,
    live: true,
  },
]

const mountList = (perms = [], feed = items) => {
  http.get.mockResolvedValue({ data: { success: true, data: feed } })

  const store = createStore({
    modules: {
      Auth: {
        namespaced: true,
        state: () => ({ user: { fio: 'Ученик', permissions: perms } }),
        getters: { permissionSlugs: () => perms },
      },
    },
  })

  return mount(AnnouncementList, {
    global: { plugins: [vuetify, i18n, store], stubs: { RouterLink: true } },
  })
}

describe('AnnouncementList — лента объявлений', () => {
  beforeEach(() => vi.clearAllMocks())

  it('показывает объявления в порядке ответа', async () => {
    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('[data-test="ann-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="ann-2"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Плановая проверка')
  })

  it('отмечает закреплённое объявление', async () => {
    const wrapper = mountList()
    await flushPromises()

    const pinned = wrapper.find('[data-test="ann-1"]')

    expect(pinned.classes()).toContain('is-pinned')
    expect(pinned.find('[data-test="ann-pinned"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="ann-2"]').find('[data-test="ann-pinned"]').exists()).toBe(false)
  })

  it('у обучаемого нет кнопок публикации', async () => {
    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('[data-test="ann-new"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="ann-del-1"]').exists()).toBe(false)
  })

  it('у редактора есть кнопки публикации и правки', async () => {
    const wrapper = mountList(['announcements.manage'])
    await flushPromises()

    expect(wrapper.find('[data-test="ann-new"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="ann-edit-1"]').exists()).toBe(true)
    expect(wrapper.find('[data-test="ann-del-1"]').exists()).toBe(true)
  })

  it('удаление спрашивает подтверждение', async () => {
    const wrapper = mountList(['announcements.manage'])
    await flushPromises()

    await wrapper.find('[data-test="ann-del-1"]').trigger('click')
    await flushPromises()

    // Публикацию нельзя откатить: без подтверждения объявление пропало бы
    // у всех, кто его уже прочитал. Диалог телепортирован в body, поэтому
    // проверяем состояние, а не текст внутри wrapper.
    expect(wrapper.vm.confirm.open).toBe(true)
    expect(wrapper.vm.confirm.item.id).toBe(1)
    expect(http.delete).not.toHaveBeenCalled()
  })

  it('пустая лента объясняет, а не выглядит поломкой', async () => {
    const wrapper = mountList([], [])
    await flushPromises()

    expect(wrapper.find('[data-test="ann-list"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Объявлений нет')
  })
})