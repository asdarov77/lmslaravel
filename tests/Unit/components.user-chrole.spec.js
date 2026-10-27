// @vitest-environment jsdom
/**
 * Страница назначения ролей (/user/chrole/:idEdit).
 *
 * Страница была нерабочей: маршрут и API PUT /api/user/chroll/{id}
 * были закомментированы (запрос уходил в 404), в заголовке выводилась
 * несуществующая переменная usernameEdit, а ошибки выводились классами
 * Bulma, которых в проекте нет, — то есть при ошибке пользователь видел
 * пустую полосу вместо текста.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

import UserChrole from '../../resources/js/Pages/User/UserChrole.vue'
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


const get = vi.fn()
const put = vi.fn()
vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get: (...a) => get(...a), put: (...a) => put(...a) },
}))

const push = vi.fn()
const $router = { push: (...a) => push(...a) }

// jsdom не реализует ResizeObserver и visualViewport, а нужны они
// v-progress-circular (спиннер загрузки) и AppToast (v-overlay).
// Здесь проверяется логика страницы, а не рендеринг Vuetify.
global.ResizeObserver = class {
  observe() {}
  unobserve() {}
  disconnect() {}
}
global.visualViewport = {
  addEventListener() {},
  removeEventListener() {},
  offsetLeft: 0,
  offsetTop: 0,
  width: 1024,
  height: 768,
  scale: 1,
}

// Ответы в том же конверте, что отдаёт ApiResponseEnvelope.
const envelope = (data) => ({ data: { success: true, data, error: null, meta: null } })

beforeEach(() => {
  vi.clearAllMocks()
  get.mockImplementation((url) => {
    if (url.includes('api/role')) {
      return Promise.resolve(
        envelope([
          { id: 1, rolename: 'Администратор', slug: 'admin' },
          { id: 2, rolename: 'Инструктор', slug: 'instructor' },
          { id: 3, rolename: 'Обучаемый', slug: 'trainee' },
        ])
      )
    }
    if (url.includes('api/v1/me')) {
      return Promise.resolve(envelope({ id: 99, fio: 'Я админ' }))
    }
    return Promise.resolve(envelope({ id: 5, fio: 'Иванов И.И.', role: 'Обучаемый', roles: [] }))
  })
  put.mockResolvedValue(envelope({ ok: true }))
})

const factory = (props = { idEdit: 5 }) =>
  mount(UserChrole, {
    props,
    global: { plugins: [vuetify, i18n], mocks: { $router } },
  })

describe('UserChrole: загрузка данных', () => {
  it('подставляет имя пользователя в заголовок вместо пустой строки', async () => {
    // Раньше в шапке было {{ usernameEdit }} — переменной нет в data,
    // поэтому имя всегда было пустым.
    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    expect(w.text()).toContain('Иванов И.И.')
    expect(get).toHaveBeenCalledWith('api/role')
  })

  it('отмечает страницу собственных ролей и не даёт сохранить её молча', async () => {
    get.mockImplementation((url) => {
      if (url.includes('api/role')) return Promise.resolve(envelope([]))
      if (url.includes('api/v1/me')) return Promise.resolve(envelope({ id: 5, fio: 'Иванов И.И.' }))
      return Promise.resolve(envelope({ id: 5, fio: 'Иванов И.И.', roles: [] }))
    })

    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    expect(w.find('[data-test="chrole-self"]').exists()).toBe(true)
  })

  it('показывает spinner, пока роли не загрузились', async () => {
    let release
    get.mockImplementation((url) => {
      if (url.includes('api/role')) return new Promise((r) => (release = () => r(envelope([]))))
      return Promise.resolve(envelope({ id: 5, fio: 'Иванов И.И.', roles: [] }))
    })

    const w = factory()
    await w.vm.$nextTick()

    // Пустая форма без индикатора выглядит как зависшая страница.
    expect(w.vm.loaded).toBe(false)
    release()
    await vi.waitUntil(() => w.vm.loaded)
  })
})

describe('UserChrole: сохранение', () => {
  it('отправляет массив role_id и уходит в список пользователей', async () => {
    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    w.vm.selected = [2, 3]
    await w.vm.submitForm()

    expect(put).toHaveBeenCalledWith('api/user/chroll/5', { role_id: [2, 3] })
    expect(push).toHaveBeenCalledWith('/user/list')
  })

  it('пустой выбор — это снятие ролей, а не ошибка валидации', async () => {
    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    w.vm.selected = []
    await w.vm.submitForm()

    expect(put).toHaveBeenCalledWith('api/user/chroll/5', { role_id: [] })
  })

  it('показывает текст ошибки сервера, а не пустую полосу', async () => {
    // Bulma-классы в проекте не работают: пользователь видел пустую
    // полосу вместо сообщения.
    put.mockRejectedValue({
      response: { data: { success: false, error: { message: 'Нельзя менять собственные роли' } } },
    })

    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    w.vm.selected = [1]
    await w.vm.submitForm()

    const shown = shownToasts()
    expect(shown.some((t) => t.type === 'error')).toBe(true)
    expect(shown.map((t) => t.text)).toContain('Нельзя менять собственные роли')
    clearToasts()
    expect(push).not.toHaveBeenCalled()
  })

  it('показывает текст ошибки валидации role_id', async () => {
    put.mockRejectedValue({
      response: { data: { success: false, errors: { role_id: ['Роль не найдена.'] } } },
    })

    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    w.vm.selected = [999]
    await w.vm.submitForm()

    expect(w.vm.fieldError).toBe('Роль не найдена.')
    expect(shownToasts().map((t) => t.text)).toContain('Роль не найдена.')
    clearToasts()
  })

  it('ошибка загрузки ролей не роняет страницу', async () => {
    get.mockImplementation((url) => {
      if (url.includes('api/role')) {
        return Promise.reject({ response: { data: { message: 'Нет доступа' } } })
      }
      return Promise.resolve(envelope({ id: 5, fio: 'Иванов И.И.', roles: [] }))
    })

    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    expect(shownToasts().map((t) => t.text)).toContain('Нет доступа')
    clearToasts()
  })
})

describe('UserChrole: Bulma не используется', () => {
  it('в разметке нет классов Bulma', async () => {
    const w = factory()
    await vi.waitUntil(() => w.vm.loaded)

    // Сравниваем ТОЧНЫЕ токены классов, а не подстроки: иначе
    // «v-input__control» матчится на /\binput\b/, и проверка врёт.
    const bulma = new Set([
      'is-danger', 'is-primary', 'is-info', 'is-success', 'is-warning',
      'is-light', 'notification', 'button', 'input', 'card', 'box',
      'columns', 'label', 'table', 'has-text-centered',
    ])

    const tokens = (w.html().match(/class="[^"]*"/g) || [])
      .flatMap((attr) => attr.slice(7, -1).split(/\s+/))
      .filter(Boolean)

    const found = [...new Set(tokens.filter((t) => bulma.has(t)))]
    expect(found, 'найдены классы Bulma').toEqual([])
  })
})
