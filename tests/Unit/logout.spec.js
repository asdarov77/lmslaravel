// @vitest-environment jsdom
/**
 * Выход из приложения.
 *
 * Проверяем порядок действий, а не только конечный маршрут: раньше
 * переход на страницу авторизации уходил в тот же тик, что и запрос
 * разлогина, — токен ещё лежал в хранилище. Приложение успевало
 * смонтировать форму регистрации «авторизованной» пользователем, она
 * уходила в /api/groups и оставляла тост «Список групп недоступен:
 * войдите в систему» сразу после выхода.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import { createI18n } from 'vue-i18n'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createStore } from 'vuex'

import AccountMenu from '../../resources/js/Pages/Navigation/AccountMenu.vue'
import LogoutApp from '../../resources/js/Pages/Navigation/LogoutApp.vue'
import ru from '../../resources/js/locales/ru.json'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const i18n = createI18n({ legacy: false, globalInjection: true, locale: 'ru', fallbackLocale: 'ru', messages: { ru } })

/**
 * Разлогин с задержкой — как в жизни: сетевой ответ не мгновенный.
 * Порядок проверяется по журналу событий.
 */
const makeHarness = () => {
  const events = []

  const store = createStore({
    modules: {
      Auth: {
        namespaced: true,
        state: { accessToken: 'token-1', user: { id: 1 }, permissionSlugs: ['users.create'], roleSlugs: [] },
        actions: {
          logout: async ({ commit }) => {
            events.push('logout:start')
            await new Promise((r) => setTimeout(r, 5))
            commit('LOGOUT')
            events.push('logout:done')
          },
        },
        mutations: {
          LOGOUT(state) {
            state.accessToken = null
            state.user = {}
            state.permissionSlugs = []
          },
        },
        getters: {
          can: (state) => () => state.permissionSlugs.includes('users.create'),
          loggedIn: (state) => !!state.accessToken,
        },
      },
      Ui: {
        namespaced: true,
        actions: { logout: async () => { events.push('ui:logout') } },
      },
    },
  })

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/courses/list', name: 'courses.list', component: { template: '<div />' } },
    ],
  })

  // Каждый переход попадает в журнал — по нему и судим порядок.
  router.afterEach((to) => events.push(`nav:${to.name}`))

  return { store, router, events }
}

const mountWith = (component, harness) =>
  mount(component, { global: { plugins: [vuetify, i18n, harness.store, harness.router] } })

beforeEach(async () => {
  const harness = makeHarness()
  await harness.router.push('/courses/list')
  await harness.router.isReady()
})

describe('Выход: меню профиля', () => {
  it('гасит сессию ДО перехода на страницу авторизации', async () => {
    const harness = makeHarness()
    await harness.router.push('/courses/list')

    const wrapper = mountWith(AccountMenu, harness)

    await wrapper.vm.logout()
    await harness.router.isReady()

    const doneIndex = harness.events.indexOf('logout:done')
    const navIndex = harness.events.indexOf('nav:login')

    expect(doneIndex).toBeGreaterThan(-1)
    expect(navIndex).toBeGreaterThan(-1)
    // Ключевое: раньше переход происходил раньше конца разлогина.
    expect(doneIndex).toBeLessThan(navIndex)

    wrapper.unmount()
  })

  it('оказывается на странице авторизации', async () => {
    const harness = makeHarness()
    await harness.router.push('/courses/list')

    const wrapper = mountWith(AccountMenu, harness)
    await wrapper.vm.logout()
    await harness.router.isReady()

    expect(harness.router.currentRoute.value.name).toBe('login')
    expect(harness.store.state.Auth.accessToken).toBeNull()

    wrapper.unmount()
  })

  it('переходит на страницу авторизации даже при ошибке сети', async () => {
    const harness = makeHarness()
    await harness.router.push('/courses/list')

    const wrapper = mountWith(AccountMenu, harness)
    // Сеть отвалилась: разлогин бросает ошибку. Пользователь всё равно
    // должен оказаться на странице входа, а не внутри приложения.
    harness.store._actions['Auth/logout'][0] = async () => { throw new Error('network down') }

    await wrapper.vm.logout()
    await harness.router.isReady()

    expect(harness.router.currentRoute.value.name).toBe('login')

    wrapper.unmount()
  })
})

describe('Выход: страница выхода', () => {
  it('соблюдает тот же порядок и чистит состояние интерфейса', async () => {
    const harness = makeHarness()
    await harness.router.push('/courses/list')

    const wrapper = mountWith(LogoutApp, harness)

    await wrapper.vm.logout()
    await harness.router.isReady()

    expect(harness.events.indexOf('logout:done')).toBeLessThan(harness.events.indexOf('nav:login'))
    expect(harness.events).toContain('ui:logout')
    expect(harness.router.currentRoute.value.name).toBe('login')

    wrapper.unmount()
  })

  it('переходит по имени маршрута, а не по строке пути', async () => {
    const harness = makeHarness()
    await harness.router.push('/courses/list')

    const wrapper = mountWith(LogoutApp, harness)
    await wrapper.vm.logout()
    await harness.router.isReady()

    // Строковый '/login' разъезжается с базой приложения при смене
    // префикса; имя маршрута к этому нечувствительно.
    expect(harness.router.currentRoute.value.name).toBe('login')
    expect(harness.router.currentRoute.value.path).toBe('/login')

    wrapper.unmount()
  })
})
