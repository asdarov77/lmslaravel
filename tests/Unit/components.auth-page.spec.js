// @vitest-environment jsdom
/**
 * Экран авторизации (Фаза 3.1): вход и регистрация на одном экране.
 *
 * Проверяем то, ради чего форма объединена:
 *  - переключение меняет форму НА МЕСТЕ, без ухода на другую страницу;
 *  - режим живёт в URL (?mode=register) — ссылкой можно поделиться,
 *    и кнопка «назад» в браузере работает предсказуемо;
 *  - у каждой формы своя схема и свои правила: пароли не совпадают —
 *    ошибка у РЕГИСТРАЦИИ, а не у входа;
 *  - введённое не теряется при переключении туда-обратно.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createStore } from 'vuex'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { nextTick } from 'vue'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import AuthPage from '../../resources/js/Pages/AuthPage.vue'
import AuthShell from '../../resources/js/components/ui/AuthShell.vue'
import ru from '../../resources/js/locales/ru.json'
import { toastItems, clear } from '../../resources/js/composables/useToast'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

const mountPage = async (initialQuery = {}) => {
  // Моки экшенов держим СНАРУЖИ: actions живут в модулях стора, а не в
  // state, поэтому подменить их через store.state нельзя.
  const actions = { login: vi.fn(async () => undefined), createUser: vi.fn(async () => undefined), fetchGroups: vi.fn(async () => undefined) }

  const store = createStore({
    modules: {
      User: {
        namespaced: true,
        state: () => ({ allGroups: [], groups: [] }),
        getters: { groups: () => [] },
        actions: { fetchGroups: actions.fetchGroups, createUser: actions.createUser },
      },
      Auth: {
        namespaced: true,
        actions: { login: actions.login },
        getters: { can: () => () => true },
      },
    },
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/:pathMatch(.*)*', name: 'catch-all', component: { template: '<div/>' } },
      { path: '/dashboard', name: 'dashboard', component: { template: '<div/>' } },
      { path: '/users/list', name: 'users.list', component: { template: '<div/>' } },
      { path: '/password/forgot', name: 'password.forgot', component: { template: '<div/>' } },
    ],
  })

  const entry = router.getRoutes()[0].path
  await router.replace({ path: entry, query: initialQuery })
  await router.isReady()

  const wrapper = mount(AuthPage, {
    global: {
      plugins: [vuetify, i18n, store, router],
      stubs: {
        VProgressCircular: { name: 'VProgressCircular', template: '<span />' },
        VTextarea: { name: 'VTextarea', props: ['modelValue'], template: '<textarea :value="modelValue" />' },
      },
    },
  })
  await flushPromises()

  return { wrapper, router, store, actions }
}

beforeEach(() => {
  vi.clearAllMocks()
  clear()
})

/**
 * Дождаться обновления маршрута.
 *
 * router.replace — асинхронный (guard'ы, резолв), и одних микрозадач
 * (flushPromises) мало: на момент проверки query ещё пустой.
 */
const routeSettled = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('AuthShell: каркас', () => {
  it('показывает переключатель только когда вкладок больше одной', () => {
    const one = mount(AuthShell, { props: { tabs: [{ key: 'a', title: 'A' }], active: 'a' }, global: { plugins: [vuetify] } })
    expect(one.find('.auth-switch').exists()).toBe(false)

    const two = mount(AuthShell, {
      props: { tabs: [{ key: 'a', title: 'A' }, { key: 'b', title: 'B' }], active: 'a' },
      global: { plugins: [vuetify] },
    })
    expect(two.findAll('.auth-switch__tab').length).toBe(2)
  })

  it('помечает активную вкладку для скринридера', () => {
    const wrapper = mount(AuthShell, {
      props: { tabs: [{ key: 'a', title: 'A' }, { key: 'b', title: 'B' }], active: 'b', switchLabel: 'Режим' },
      global: { plugins: [vuetify] },
    })

    const active = wrapper.findAll('.auth-switch__tab')[1]
    expect(active.attributes('aria-selected')).toBe('true')
    expect(active.attributes('role')).toBe('tab')
  })
})

describe('AuthPage: переключение вход ⇄ регистрация', () => {
  it('по умолчанию открыт вход', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.vm.mode).toBe('login')
    expect(wrapper.find('[data-test="auth-tab-login"].is-active').exists()).toBe(true)
    wrapper.unmount()
  })

  it('открывает регистрацию по ?mode=register — ссылкой можно поделиться', async () => {
    const { wrapper } = await mountPage({ mode: 'register' })

    expect(wrapper.vm.mode).toBe('register')
    expect(wrapper.find('[data-test="auth-tab-register"].is-active').exists()).toBe(true)
    wrapper.unmount()
  })

  it('переключение меняет форму на месте и отражается в URL', async () => {
    const { wrapper, router } = await mountPage()

    await wrapper.vm.setMode('register')
    await flushPromises()
    await routeSettled()

    expect(wrapper.vm.mode).toBe('register')
    expect(router.currentRoute.value.query.mode).toBe('register')
    // Панель бренда и её содержимое НЕ перерисовываются при смене режима.
    expect(wrapper.find('.auth-layout__brand').exists()).toBe(true)

    wrapper.unmount()
  })

  it('возврат во вход убирает режим из URL', async () => {
    const { wrapper, router } = await mountPage({ mode: 'register' })

    await wrapper.vm.setMode('login')
    await flushPromises()
    await routeSettled()

    expect(wrapper.vm.mode).toBe('login')
    expect(router.currentRoute.value.query.mode).toBeUndefined()
    wrapper.unmount()
  })

  it('неизвестный режим в URL не ломает страницу', async () => {
    const { wrapper } = await mountPage({ mode: 'какая-то дичь' })

    expect(wrapper.vm.mode).toBe('login')
    wrapper.unmount()
  })
})

describe('AuthPage: формы', () => {
  it('у входа и регистрации разные поля', async () => {
    const { wrapper } = await mountPage()

    expect(wrapper.find('#userLogin').exists()).toBe(true)
    expect(wrapper.find('#password_confirmation').exists()).toBe(false)

    await wrapper.vm.setMode('register')
    await flushPromises()

    expect(wrapper.find('#password_confirmation').exists()).toBe(true)

    wrapper.unmount()
  })

  it('у каждой формы своя схема: введённый логин не уезжает в регистрацию', async () => {
    const { wrapper } = await mountPage()

    wrapper.vm.login.values.fio = 'Сохранённый логин'
    await nextTick()

    await wrapper.vm.setMode('register')
    await flushPromises()

    expect(wrapper.vm.login.values.fio).toBe('Сохранённый логин')
    expect(wrapper.vm.register.values.fio).toBe('')

    wrapper.unmount()
  })

  it('несовпадение паролей — ошибка регистрации, а не входа', async () => {
    const { wrapper, actions } = await mountPage({ mode: 'register' })

    wrapper.vm.register.values.fio = 'Новый'
    wrapper.vm.register.values.password = 'secret123'
    wrapper.vm.register.values.password_confirmation = 'other456'
    await nextTick()

    await wrapper.vm.submitRegister()
    await flushPromises()

    expect(actions.createUser, 'запрос на регистрацию уходить не должен').not.toHaveBeenCalled()
    expect(wrapper.vm.register.errors.password_confirmation).toBe('Пароли не совпадают')
    expect(wrapper.vm.login.errors.password_confirmation).toBeUndefined()

    wrapper.unmount()
  })

  it('вход требует заполненных полей до запроса', async () => {
    const { wrapper } = await mountPage()

    await wrapper.vm.submitLogin()
    await flushPromises()

    expect(wrapper.vm.login.errors.fio).toBe('Укажите ФИО')
    expect(wrapper.vm.login.errors.password).toBe('Укажите пароль')

    wrapper.unmount()
  })

  it('при неверном пароле показывается тост «Неверный пароль»', async () => {
    const { wrapper, actions } = await mountPage()

    actions.login.mockRejectedValueOnce({ response: { status: 401 } })
    wrapper.vm.login.values.fio = 'Администратор'
    wrapper.vm.login.values.password = 'неверный'
    await nextTick()

    await wrapper.vm.submitLogin()
    await flushPromises()

    expect(toastItems.some((t) => t.type === 'error' && t.text === 'Неверный пароль')).toBe(true)

    wrapper.unmount()
  })
})

/**
 * 429: обратный отсчёт вместо голого «слишком много запросов».
 *
 * Без времени ожидания пользователь либо жмёт снова (и снова получает
 * 429), либо уходит с вопроса «а когда можно». Число секунд берётся из
 * заголовков ответа.
 */
describe('AuthPage: ограничение частоты', () => {
  it('показывает отсчёт и не пускает повторную отправку', async () => {
    const { wrapper, actions } = await mountPage()

    actions.login.mockRejectedValueOnce({
      response: { status: 429, headers: { get: (name) => (name === 'Retry-After' ? '30' : null) } },
    })
    wrapper.vm.login.values.fio = 'Администратор'
    wrapper.vm.login.values.password = 'неверный'
    await nextTick()

    await wrapper.vm.submitLogin()
    await flushPromises()

    expect(wrapper.vm.rateLimit.seconds.value).toBe(30)
    expect(wrapper.find('[data-test="login-ratelimit"]').exists()).toBe(true)

    // Пока тикает ожидание, вторая попытка не уходит на сервер.
    const callsBefore = actions.login.mock.calls.length
    await wrapper.vm.submitLogin()
    expect(actions.login.mock.calls.length).toBe(callsBefore)

    wrapper.unmount()
  })

  it('после окончания ожидания форма снова принимает отправку', async () => {
    vi.useFakeTimers()
    const { wrapper, actions } = await mountPage()

    actions.login.mockRejectedValueOnce({
      response: { status: 429, headers: { get: (name) => (name === 'Retry-After' ? '2' : null) } },
    })
    wrapper.vm.login.values.fio = 'Администратор'
    wrapper.vm.login.values.password = 'неверный'
    await nextTick()

    await wrapper.vm.submitLogin()
    await flushPromises()
    expect(wrapper.vm.rateLimit.active.value).toBe(true)

    vi.advanceTimersByTime(2100)
    await flushPromises()
    expect(wrapper.vm.rateLimit.active.value).toBe(false)

    vi.useRealTimers()
    wrapper.unmount()
  })
})
