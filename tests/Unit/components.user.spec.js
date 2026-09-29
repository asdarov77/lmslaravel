// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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
import UserModule from '../../resources/js/Store/modules/UserModule'
import AuthModule from '../../resources/js/Store/modules/AuthModule'

const vuetify = createVuetify({ components, directives })
const i18n = createI18n({
  legacy: false,
  locale: 'ru',
  messages: {
    ru: { app: { buttons: { save: 'сохранить', cancel: 'отмена' } } },
    en: { app: { buttons: { save: 'save', cancel: 'cancel' } } },
  },
})

import UserItemEdit from '../../resources/js/Pages/User/UserItemEdit.vue'
import Register from '../../resources/js/Pages/Register.vue'
import UserPage from '../../resources/js/Pages/User/UserPage.vue'
import GroupList from '../../resources/js/Pages/GroupList.vue'

// Конверт ровно такой, как отдаёт middleware ApiResponseEnvelope
const envelope = data => ({ data: { success: true, data, error: null, meta: null } })

const tick = () => new Promise(r => setTimeout(r, 0))

let store
let errors
let origError

beforeEach(() => {
  vi.clearAllMocks()
  errors = []
  origError = console.error
  console.error = (...a) => errors.push(a.map(String).join(' '))
  store = createStore({
    modules: {
      User: UserModule,
      // GroupList/Register используют геттер hasPermission
      Auth: { ...AuthModule, getters: { hasPermission: () => () => true } },
    },
  })
})

afterEach(() => {
  console.error = origError
})

const VTextField = {
  name: 'VTextField',
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template: '<input :value="modelValue">',
}

const passthroughStub = name => ({
  name,
  props: ['modelValue', 'items', 'itemValue', 'itemTitle', 'label', 'variant', 'dense', 'clearable', 'attach', 'type'],
  template: '<div class="' + name.toLowerCase() + '-stub"></div>',
})

const mountPage = component =>
  mount(component, {
    store,
    vuetify,
    i18n,
    global: {
      mocks: {
        // provide: точка монтирования не ставит стор в $store,
        // поэтому передаём его явно через mocks
        $store: store,
        $route: { params: { idEdit: '37' }, push: vi.fn(), back: vi.fn() },
        $router: { push: vi.fn(), back: vi.fn() },
      },
      stubs: {
        VTextField,
        VSelect: passthroughStub('VSelect'),
        VCombobox: passthroughStub('VCombobox'),
        VBtn: { name: 'VBtn', template: '<button><slot/></button>' },
        ButtonGroup: { name: 'ButtonGroup', template: '<div/>' },
      },
    },
  })

// ------------------------------------------------- РЕГРЕСС: group_id-объект

describe('UserItemEdit: нормализация group_id', () => {
  it('объект из v-combobox превращается в number, а не уходит в API объектом', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Тест', group_id: null }))
      }
      return Promise.resolve(envelope([]))
    })
    http.patch.mockResolvedValue(envelope({ id: 37, fio: 'Тест' }))

    const wrapper = mountPage(UserItemEdit)
    await tick()

    // Имитируем то, что реально отдавал v-combobox вместо id.
    // user берётся из стора (mapState), поэтому пишем в стор.
    store.commit('User/SET_USER', { id: 37, fio: 'Тест', group_id: { id: 1, groupname: 'ducimus' } })
    await wrapper.vm.submitForm()
    await tick()

    const [, payload] = http.patch.mock.calls[0]
    expect(typeof payload.group_id).not.toBe('object')
    expect(payload.group_id).toBe(1)
  })

  it('пустая строка, null, undefined, 0 и мусор дают null', () => {
    const wrapper = mountPage(UserItemEdit)
    expect(wrapper.vm.normalizeGroupId('')).toBeNull()
    expect(wrapper.vm.normalizeGroupId(null)).toBeNull()
    expect(wrapper.vm.normalizeGroupId(undefined)).toBeNull()
    expect(wrapper.vm.normalizeGroupId(0)).toBeNull()
    expect(wrapper.vm.normalizeGroupId('abc')).toBeNull()
    expect(wrapper.vm.normalizeGroupId(-3)).toBeNull()
    expect(wrapper.vm.normalizeGroupId({})).toBeNull()
    expect(wrapper.vm.normalizeGroupId({ id: '' })).toBeNull()
  })

  it('числовая строка приводится к числу', () => {
    const wrapper = mountPage(UserItemEdit)
    expect(wrapper.vm.normalizeGroupId('5')).toBe(5)
    expect(wrapper.vm.normalizeGroupId(7)).toBe(7)
  })

  it('остальные поля профиля не затираются нормализацией', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Тест', group_id: 3 }))
      }
      return Promise.resolve(envelope([]))
    })
    http.patch.mockResolvedValue(envelope({ id: 37 }))

    const wrapper = mountPage(UserItemEdit)
    await tick()

    store.commit('User/SET_USER', {
      id: 37,
      fio: 'Полное Имя',
      city: 'Москва',
      position: 'Инженер',
      group_id: 3,
    })
    await wrapper.vm.submitForm()
    await tick()

    const [, payload] = http.patch.mock.calls[0]
    expect(payload.fio).toBe('Полное Имя')
    expect(payload.city).toBe('Москва')
    expect(payload.position).toBe('Инженер')
    expect(payload.group_id).toBe(3)
  })
})

// ------------------------------------------------------ РЕГРЕСС: конверт

describe('UserItemEdit: разворачивание конверта', () => {
  it('fetchUser кладёт в state объект, а не конверт', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Пользователь', group_id: 2 }))
      }
      return Promise.resolve(envelope([{ id: 1, groupname: 'Группа' }]))
    })

    mountPage(UserItemEdit)
    await tick()

    expect(store.state.User.user).toMatchObject({ id: 37, fio: 'Пользователь' })
    expect(store.state.User.user.success).toBeUndefined()
    expect(Array.isArray(store.state.User.allGroups)).toBe(true)
  })

  it('null от сервера не ломает страницу', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) return Promise.resolve(envelope(null))
      return Promise.resolve(envelope(null))
    })

    mountPage(UserItemEdit)
    await tick()

    expect(store.state.User.allGroups).toEqual([])
  })
})

// ------------------------------------------------------- РЕГРЕСС: группы

describe('GroupList: отрисовка без ошибок', () => {
  it('конверт разворачивается в массив, сортировка не падает', async () => {
    http.get.mockResolvedValue(
      envelope([
        { id: 2, groupname: 'Б', groupdescription: 'вторая' },
        { id: 1, groupname: 'А', groupdescription: 'первая' },
      ])
    )

    mountPage(GroupList)
    await tick()

    const groups = store.getters['User/groups']
    expect(Array.isArray(groups)).toBe(true)
    expect(groups).toHaveLength(2)
    expect(errors.join(' ')).not.toMatch(/sort is not a function/)
  })

  it('пустой ответ даёт пустой массив, а не конверт', async () => {
    http.get.mockResolvedValue(envelope([]))
    mountPage(GroupList)
    await tick()
    expect(store.getters['User/groups']).toEqual([])
  })
})

// ------------------------------------------------ РЕГРЕСС: форма создания

describe('Register: создание пользователя', () => {
  it('успешный ответ показывает диалог и кладёт пользователя в state', async () => {
    http.post.mockImplementation((url, payload) => {
      if (url === '/api/register') {
        return Promise.resolve(envelope({ user: { id: 99, fio: payload.fio } }))
      }
      return Promise.resolve(envelope([]))
    })

    const wrapper = mountPage(Register)
    await tick()

    // Register держит поля в собственных data, а не в сторе
    wrapper.vm.fio = 'Новый Сотрудник'
    wrapper.vm.password = 'secret123'
    wrapper.vm.password_confirmation = 'secret123'
    await wrapper.vm.submitForm()
    await tick()

    const [url, payload] = http.post.mock.calls[0]
    expect(url).toBe('/api/register')
    expect(payload.fio).toBe('Новый Сотрудник')
    expect(store.state.User.user).toMatchObject({ id: 99 })
  })

  it('не отправляет форму, если пароли не совпадают', async () => {
    const wrapper = mountPage(Register)
    await tick()

    wrapper.vm.fio = 'Новый'
    wrapper.vm.password = 'aaa'
    wrapper.vm.password_confirmation = 'bbb'
    await wrapper.vm.submitForm()
    await tick()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.vm.errors).toContain('пароли не совпадают')
  })

  it('не отправляет форму без fio', async () => {
    const wrapper = mountPage(Register)
    await tick()

    wrapper.vm.fio = ''
    wrapper.vm.password = 'aaa'
    wrapper.vm.password_confirmation = 'aaa'
    await wrapper.vm.submitForm()
    await tick()

    expect(http.post).not.toHaveBeenCalled()
  })

  it('ошибка не роняет страницу', async () => {
    http.post.mockRejectedValue({ response: { status: 500, data: { message: 'boom' } } })
    const wrapper = mountPage(Register)
    await tick()

    wrapper.vm.fio = 'Ошибка'
    wrapper.vm.password = 'secret123'
    wrapper.vm.password_confirmation = 'secret123'
    await wrapper.vm.submitForm()
    await tick()

    expect(wrapper.vm.alertType).toBe('error')

    expect(errors.join(' ')).not.toMatch(/Cannot read propert/)
  })
})

// ---------------------------------------- РЕГРЕСС: /auk -> UserPage.vue

describe('UserPage: рендер без pageerror', () => {
  it('страница рендерится, даже когда user ещё не пришёл', () => {
    // Раньше шаблон содержал {{ user.fio }} без guard'а: компонент без
    // <script> вообще не определяет user, и рендер падал с
    // «Cannot read properties of undefined (reading 'fio')».
    http.get.mockResolvedValue(envelope(null))

    expect(() => mountPage(UserPage)).not.toThrow()

    const wrapper = mountPage(UserPage)
    expect(wrapper.text()).toContain('Добро пожаловать')
  })
})

// --------------------- РЕГРЕСС: пустой выпадающий список групп (v-select)

describe('UserItemEdit: список групп для выпадающего меню', () => {
  it('при монтировании запрашивает группы, а не только пользователя', async () => {
    const groups = [
      { id: 1, groupname: 'Лётчики' },
      { id: 2, groupname: 'Борт-инженеры' },
    ]
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Тест', group_id: 1 }))
      }
      if (url === '/api/groups') {
        return Promise.resolve(envelope(groups))
      }
      return Promise.resolve(envelope([]))
    })

    mountPage(UserItemEdit)
    await tick()

    // Без этого запроса allGroups оставался [] и v-select был пуст.
    expect(http.get).toHaveBeenCalledWith('/api/groups')
    expect(store.state.User.allGroups).toHaveLength(2)
  })

  it('в группу можно выбрать одну из загруженных групп', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Тест', group_id: null }))
      }
      if (url === '/api/groups') {
        return Promise.resolve(envelope([
          { id: 1, groupname: 'Лётчики' },
          { id: 2, groupname: 'Борт-инженеры' },
        ]))
      }
      return Promise.resolve(envelope([]))
    })
    http.patch.mockResolvedValue(envelope({ id: 37, fio: 'Тест', group_id: 2 }))

    const wrapper = mountPage(UserItemEdit)
    await tick()

    // v-select пишет выбранный id прямо в user.group_id
    store.commit('User/SET_USER', { id: 37, fio: 'Тест', group_id: 2 })
    await wrapper.vm.submitForm()
    await tick()

    const [, payload] = http.patch.mock.calls[0]
    expect(payload.group_id).toBe(2)
  })

  it('падение загрузки групп не роняет страницу и попадает в errors', async () => {
    http.get.mockImplementation(url => {
      if (url.startsWith('/api/user/list/')) {
        return Promise.resolve(envelope({ id: 37, fio: 'Тест', group_id: null }))
      }
      if (url === '/api/groups') {
        return Promise.reject(new Error('groups unavailable'))
      }
      return Promise.resolve(envelope([]))
    })

    const wrapper = mountPage(UserItemEdit)
    await tick()

    expect(wrapper.vm.errors.join(' ')).toMatch(/групп/i)
  })
})
