// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'

/**
 * Страница управления правами (/permissions).
 *
 * Регрессии, которые закрывает файл:
 *
 *  1. Права уехали из списка пользователей в отдельный раздел, но
 *     заблокированные (вне полномочий актора) оставались в отправляемом
 *     наборе. Сервер отвергает запрос, если в нём есть хоть одно право,
 *     которое актор выдать не может, — поэтому инструктор с правом
 *     users.create у подопечного не мог сохранить вообще ничего.
 *  2. Раньше computed открывал доступ к разделу всем, у кого есть
 *     users.view — интерфейс показывал кнопку, которая вела в 403.
 */

const get = vi.hoisted(() => vi.fn())
const put = vi.hoisted(() => vi.fn())
vi.mock('../../resources/js/api/httpClient', () => ({
  default: { get, put, post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'

import UserModule from '../../resources/js/Store/modules/UserModule'
import AuthModule from '../../resources/js/Store/modules/AuthModule'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'
import PermissionsManager from '../../resources/js/Pages/Permissions/PermissionsManager.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
// globalInjection нужен, чтобы this.$t в Options API-шаблоне работал
// под тестом: в юнит-тестах плагин ставится явно, а не приложением.
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', messages: { ru, en } })

const envelope = data => ({ data: { success: true, data, error: null, meta: null } })

/** Каталог прав: 4 в разделе users (2 выдаваемы администратору) и 1 в system. */
const catalog = [
  {
    name: 'Пользователи',
    permissions: [
      { id: 1, slug: 'users.view', name: 'Просмотр пользователей', assignable: true },
      { id: 2, slug: 'users.create', name: 'Создание пользователей', assignable: true },
      { id: 3, slug: 'users.delete', name: 'Удаление пользователей', assignable: false },
    ],
  },
  {
    name: 'Система',
    permissions: [{ id: 4, slug: 'system.maintenance', name: 'Обслуживание', assignable: false }],
  },
]

const users = [
  { id: 10, fio: 'Иванов', role: 'Инструктор', is_admin: false, permissions: [{ id: 1, slug: 'users.view', name: 'Просмотр' }] },
  { id: 11, fio: 'Петров', role: 'Обучаемый', is_admin: false, permissions: [] },
]

let store
let isSuperAdmin
let consoleErrors

const tick = () => new Promise(r => setTimeout(r, 0))

const mountPage = (routeQuery = {}) =>
  mount(PermissionsManager, {
    global: {
      // В VTU v2 плагины ставятся только через global.plugins: опция
      // i18n верхнего уровня осталась от первой версии и молча игнорируется,
      // из-за чего this.$t в шаблоне был undefined.
      plugins: [i18n],
      mocks: {
        $store: store,
        $route: { query: routeQuery, params: {} },
        $router: { push: vi.fn(), back: vi.fn() },
      },
      stubs: {
        'v-alert': true,
        'v-checkbox': true,
        'v-progress-linear': true,
        popup: true,
      },
    },
  })

beforeEach(() => {
  vi.clearAllMocks()
  consoleErrors = []
  const orig = console.error
  console.error = (...a) => consoleErrors.push(a.map(String).join(' '))
  afterEach(() => {
    console.error = orig
  })

  isSuperAdmin = false
  store = createStore({
    modules: {
      User: UserModule,
      Auth: {
        ...AuthModule,
        getters: {
          ...AuthModule.getters,
          isSuperAdmin: () => isSuperAdmin,
          loggedIn: () => true,
          hasPermission: () => () => true,
        },
      },
    },
  })

  get.mockImplementation(url => {
    if (url.includes('/permissions/catalog')) return Promise.resolve(envelope(catalog))
    if (url.includes('/user/manageable')) return Promise.resolve(envelope(users))
    return Promise.resolve(envelope([]))
  })
  put.mockResolvedValue(envelope(users[0]))
})

describe('PermissionsManager: загрузка', () => {
  it('подтягивает каталог и список доступных пользователей', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    expect(get).toHaveBeenCalledWith('/api/permissions/catalog')
    expect(get).toHaveBeenCalledWith('/api/user/manageable')
    expect(wrapper.vm.permissionGroups).toHaveLength(2)
    expect(wrapper.vm.manageableUsers).toHaveLength(2)
    expect(wrapper.vm.loadError).toBe('')
  })

  it('при 403 говорит прямо о недостатке прав, а не показывает пустые списки', async () => {
    get.mockRejectedValue(Object.assign(new Error('forbidden'), { response: { status: 403 } }))

    const wrapper = mountPage()
    await tick()
    await tick()

    expect(wrapper.vm.loadError).toBe('Недостаточно прав для просмотра раздела прав')
  })

  it('раскрывает разделы прав: без этого их содержимое не рендерится', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()

    expect(wrapper.vm.openGroups).toEqual(['Пользователи', 'Система'])
  })
})

describe('PermissionsManager: различия интерфейса по роли', () => {
  it('администратору видны все разделы', async () => {
    isSuperAdmin = true
    const wrapper = mountPage()
    await tick()
    await tick()

    expect(wrapper.vm.visibleGroups.map(g => g.name)).toEqual(['Пользователи', 'Система'])
  })

  it('инструктору не показывается раздел, где нет ни одного выдаваемого права', async () => {
    // Убираем у инструктора все права раздела «Пользователи»
    const asInstructor = catalog.map(g => ({
      ...g,
      permissions: g.permissions.map(p => (p.assignable ? { ...p, assignable: false } : p)),
    }))
    get.mockImplementation(url =>
      url.includes('/permissions/catalog') ? Promise.resolve(envelope(asInstructor)) : Promise.resolve(envelope(users))
    )

    const wrapper = mountPage()
    await tick()
    await tick()

    expect(wrapper.vm.visibleGroups).toEqual([])
  })
})

describe('PermissionsManager: отметка прав', () => {
  it('заблокированное право нельзя включить даже кликом', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(10)

    const locked = catalog[0].permissions.find(p => p.slug === 'users.delete')
    wrapper.vm.togglePermission(locked, true)

    expect(wrapper.vm.draft).not.toContain(locked.id)
    expect(wrapper.vm.selected).not.toContain(locked.id)
  })

  it('право вне полномочий остаётся отмеченным, но не попадает в отправку', async () => {
    // У пользователя есть users.delete — право вне полномочий актора.
    // Оно показано включённым, но сохраняться не должно: иначе сервер
    // отвергнет весь запрос.
    get.mockImplementation(url =>
      url.includes('/permissions/catalog')
        ? Promise.resolve(envelope(catalog))
        : Promise.resolve(
            envelope([
              {
                id: 10,
                fio: 'Петров',
                role: 'Обучаемый',
                is_admin: false,
                permissions: [{ id: 1, slug: 'users.view' }, { id: 3, slug: 'users.delete' }],
              },
            ])
          )
    )

    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(10)

    expect(wrapper.vm.draft).toEqual(expect.arrayContaining([1, 3]))
    expect(wrapper.vm.selected, 'в сохранение идут только выдаваемые права').toEqual([1])
  })

  it('«выбрать все» по разделу отмечает только выдаваемые права', async () => {
    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(10)

    wrapper.vm.toggleGroup(catalog[0])
    expect(wrapper.vm.draft).toEqual(expect.arrayContaining([1, 2]))
    expect(wrapper.vm.draft).not.toContain(3)

    wrapper.vm.toggleGroup(catalog[0])
    expect(wrapper.vm.draft).toEqual([])
  })

  it('интерфейс доверяет признаку assignable от бэкенда', async () => {
    // Администратору сервер присылает все права как выдаваемые — интерфейс
    // не решает это самостоятельно. Поэтому проверяем на каталоге,
    // где всё доступно.
    isSuperAdmin = true
    get.mockImplementation(url =>
      url.includes('/permissions/catalog')
        ? Promise.resolve(
            envelope(
              catalog.map(g => ({
                ...g,
                permissions: g.permissions.map(p => ({ ...p, assignable: true })),
              }))
            )
          )
        : Promise.resolve(envelope(users))
    )

    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(11)

    // Группа берётся из стора — там актуальный ответ бэкенда.
    wrapper.vm.toggleGroup(wrapper.vm.permissionGroups[1])
    expect(wrapper.vm.selected).toContain(4)
  })
})

describe('PermissionsManager: сохранение', () => {
  it('отправляет только выдаваемые права', async () => {
    get.mockImplementation(url =>
      url.includes('/permissions/catalog')
        ? Promise.resolve(envelope(catalog))
        : Promise.resolve(
            envelope([
              {
                id: 10,
                fio: 'Петров',
                role: 'Обучаемый',
                is_admin: false,
                permissions: [{ id: 3, slug: 'users.delete' }],
              },
            ])
          )
    )

    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(10)
    await wrapper.vm.save()

    expect(put).toHaveBeenCalledWith('/api/user/chperm/10', { permission_id: [] })
    expect(wrapper.vm.snackbarText).toBe('Права сохранены')
  })

  it('при 403 показывает понятный текст, а не молчит', async () => {
    put.mockRejectedValue(Object.assign(new Error('forbidden'), { response: { status: 403 } }))

    const wrapper = mountPage()
    await tick()
    await tick()
    wrapper.vm.selectUser(10)
    await wrapper.vm.save()

    expect(wrapper.vm.snackbarText).toBe('Недостаточно прав для изменения прав этого пользователя')
    expect(wrapper.vm.alertType).toBe('error')
  })
})

describe('PermissionsManager: переход из списка пользователей', () => {
  it('открывает пользователя из query-параметра', async () => {
    const wrapper = mountPage({ user: '11' })
    await tick()
    await tick()

    expect(wrapper.vm.selectedId).toBe(11)
    expect(wrapper.vm.selectedUser.fio).toBe('Петров')
  })

  it('мусорный идентификатор игнорируется', async () => {
    const wrapper = mountPage({ user: 'не число' })
    await tick()
    await tick()

    expect(wrapper.vm.selectedId).toBeNull()
  })
})