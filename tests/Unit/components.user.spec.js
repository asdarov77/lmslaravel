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
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import { createI18n } from 'vue-i18n'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import UserModule from '../../resources/js/Store/modules/UserModule'
import AuthModule from '../../resources/js/Store/modules/AuthModule'

const vuetify = createVuetify({ components, directives })
const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: 'ru',
  messages: {
    ru: { app: { buttons: { save: 'сохранить', cancel: 'отмена' } }, ...ru },
    en: { app: { buttons: { save: 'save', cancel: 'cancel' } }, ...en },
  },
})

import UserItemEdit from '../../resources/js/Pages/User/UserItemEdit.vue'
import AuthPage from '../../resources/js/Pages/AuthPage.vue'
import GroupList from '../../resources/js/Pages/GroupList.vue'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'
import { toastItems, clear } from '../../resources/js/composables/useToast'

// Конверт ровно такой, как отдаёт middleware ApiResponseEnvelope
const envelope = data => ({ data: { success: true, data, error: null, meta: null } })
/*
 * Уведомления ушли в общую очередь composables/useToast, поэтому тесты
 * смотрят её и очищают после себя: очередь переживает тест, и
 * сообщение из одного утекло бы в следующий.
 */

const shownToasts = () => toastItems.map(({ type, text }) => ({ type, text }))
const clearToasts = () => clear()


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
      // Страницы пользователей используют геттер can (hasPermission
      // помечен как устаревший).
      Auth: { ...AuthModule, getters: { can: () => () => true } },
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

const mountPage = (component, setup = {}) => {
  // Страницы на <script setup> берут стор и роутер через useStore()/useRouter(),
  // которые читают их из provide, а не из $store/$router. Поэтому плагины
  // обязаны стоять: одних mocks недостаточно — страница падала бы на
  // undefined. Роутер создаётся на каждый вызов, чтобы состояние навигации
  // не протекало между тестами.
  const router = createRouter({
    history: createMemoryHistory(),
    // Страницы используют <router-link :to="{ name: ... }">. Без самих
    // маршрутов useLink бросает «No match for {name: ...}» прямо в
    // setup — это всплывало как unhandled rejection уже ПОСЛЕ успешных
    // тестов и выглядело как «тесты зеленые, а ошибки есть».
    routes: [
      { path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } },
      { path: '/dashboard', name: 'dashboard', component: { template: '<div/>' } },
      { path: '/groups/edit/:idEdit', name: 'groups.update', component: { template: '<div/>' } },
      { path: '/permissions', name: 'permissions.manage', component: { template: '<div/>' } },
      { path: '/forgot', name: 'password.forgot', component: { template: '<div/>' } },
    ],
  })

  return mount(component, {
    global: {
      // i18n ставится плагином: опция верхнего уровня осталась от
      // VTU v1 и молча игнорируется, из-за чего $t был недоступен.
      plugins: [i18n, store, router],
      mocks: {
        $store: store,
        $route: { params: { idEdit: '37' }, push: vi.fn(), back: vi.fn() },
        $router: { push: vi.fn(), back: vi.fn(), replace: vi.fn() },
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
}

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
    // После перевода на useForm значения живут в схеме формы, а не в
    // store.state.User: править стор напрямую больше не влияет на форму.
    wrapper.vm.form.values.group_id = { id: 1, groupname: 'ducimus' }
    wrapper.vm.form.values.fio = 'Тест'
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

    wrapper.vm.form.values.fio = 'Полное Имя'
    wrapper.vm.form.values.city = 'Москва'
    wrapper.vm.form.values.position = 'Инженер'
    wrapper.vm.form.values.group_id = 3
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

describe('AuthPage: регистрация', () => {
  /*
   * Контракт менялся дважды.
   *
   * Фаза 2 — перевод на useForm: полей в data больше нет, значения живут
   * в схеме, а «пароли не совпадают» показывается ПОД полем повторного
   * ввода, а не в общем списке строк.
   *
   * Фаза 3.1 — отдельная страница Register.vue упразднена: регистрация
   * стала вторым режимом AuthPage, который открывается по ?mode=register.
   */
  const mountRegister = () => mountPage(AuthPage, { mode: 'register' })

  const fill = (wrapper, { fio, password, confirmation }) => {
    wrapper.vm.register.values.fio = fio
    wrapper.vm.register.values.password = password
    wrapper.vm.register.values.password_confirmation = confirmation
  }

  it('успешный ответ показывает диалог и кладёт пользователя в state', async () => {
    http.post.mockImplementation((url, payload) => {
      if (url === '/api/register') {
        return Promise.resolve(envelope({ user: { id: 99, fio: payload.fio } }))
      }
      return Promise.resolve(envelope([]))
    })

    const wrapper = mountRegister()
    await tick()

    fill(wrapper, { fio: 'Новый Сотрудник', password: 'secret123', confirmation: 'secret123' })
    await wrapper.vm.submitRegister()
    await tick()

    const [url, payload] = http.post.mock.calls[0]
    expect(url).toBe('/api/register')
    expect(payload.fio).toBe('Новый Сотрудник')
    expect(store.state.User.user).toMatchObject({ id: 99 })
  })

  it('не отправляет форму, если пароли не совпадают', async () => {
    const wrapper = mountRegister()
    await tick()

    fill(wrapper, { fio: 'Новый', password: 'secret123', confirmation: 'other456' })
    await wrapper.vm.submitRegister()
    await tick()

    expect(http.post).not.toHaveBeenCalled()
    // Ошибка принадлежит полю, а не общему списку строк в шаблоне.
    expect(wrapper.vm.register.errors.password_confirmation).toBe('Пароли не совпадают')
    // Первый пароль валиден по остальным правилам — иначе подсветились бы
    // оба поля и ошибка «пароли не совпадают» потерялась бы среди прочих.
    expect(wrapper.vm.register.errors.password).toBe('')
  })

  it('не отправляет форму без fio', async () => {
    const wrapper = mountRegister()
    await tick()

    fill(wrapper, { fio: '', password: 'secret123', confirmation: 'secret123' })
    await wrapper.vm.submitRegister()
    await tick()

    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.vm.register.errors.fio).toBe('Укажите ФИО')
  })

  it('ошибка не роняет страницу', async () => {
    http.post.mockRejectedValue({ response: { status: 500, data: { message: 'boom' } } })
    const wrapper = mountRegister()
    await tick()

    fill(wrapper, { fio: 'Ошибка', password: 'secret123', confirmation: 'secret123' })
    await wrapper.vm.submitRegister()
    await tick()

    expect(shownToasts().some((t) => t.type === 'error')).toBe(true)
    clearToasts()

    expect(errors.join(' ')).not.toMatch(/Cannot read propert/)
  })
})

/*
 * РЕГРЕСС удалённой страницы /auk.
 *
 * Здесь был тест «UserPage рендерится без pageerror». Он относился к
 * странице, которой больше нет: /auk показывал ту же таблицу
 * учебного плана, но без оформления, и был удалён вместе с пунктом
 * меню. Тест на удалённый файл не возвращается — если /auk вернут,
 * вернётся и регресс.
 */

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

    // v-select пишет выбранный id в значения формы.
    wrapper.vm.form.values.fio = 'Тест'
    wrapper.vm.form.values.group_id = 2
    await wrapper.vm.submitForm()
    await tick()

    const [, payload] = http.patch.mock.calls[0]
    expect(payload.group_id).toBe(2)
  })

  it('падение загрузки групп не роняет страницу и показывается тостом', async () => {
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

    // Раньше текст складывался в собственный массив errors компонента.
    // После перевода на useForm ошибки живут в форме, а недоступность
    // справочника групп сообщается тостом.
    const warnings = shownToasts().filter((t) => t.type === 'warning')
    expect(warnings.length).toBeGreaterThan(0)
    expect(warnings.map((t) => t.text).join(' ')).toMatch(/групп/i)
    clearToasts()
  })
})
