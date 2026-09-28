import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createStore } from 'vuex'

vi.mock('../../resources/js/api/auth.api', () => ({
  login: vi.fn(),
}))

import { login as loginApi } from '../../resources/js/api/auth.api'
import AuthModule from '../../resources/js/Store/modules/AuthModule'

function makeStore() {
  return createStore({
    modules: { Auth: JSON.parse(JSON.stringify({ namespaced: true })) && { ...AuthModule, state: () => AuthModule.state() } },
  })
}

describe('AuthModule', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('initializes empty state without token', () => {
    const state = AuthModule.state()
    expect(state.accessToken).toBeNull()
    expect(state.user).toEqual({})
    expect(state.errors).toBeNull()
  })

  it('LOGIN_SUCCESS sets token and clears errors', () => {
    const state = AuthModule.state()
    state.errors = ['bad']
    AuthModule.mutations.LOGIN_SUCCESS(state, 'tok123')
    expect(state.accessToken).toBe('tok123')
    expect(state.errors).toBeNull()
  })

  it('LOGIN_ERROR stores server errors', () => {
    const state = AuthModule.state()
    AuthModule.mutations.LOGIN_ERROR(state, { email: ['invalid'] })
    expect(state.errors).toEqual({ email: ['invalid'] })
  })

  it('SET_USER / SET_LANGUAGE update state', () => {
    const state = AuthModule.state()
    AuthModule.mutations.SET_USER(state, { id: 1, fio: 'Ivanov' })
    AuthModule.mutations.SET_LANGUAGE(state, 'en')
    expect(state.user.fio).toBe('Ivanov')
    expect(state.language).toBe('en')
  })

  it('LOGOUT_SUCCESS resets state', () => {
    const state = AuthModule.state()
    state.accessToken = 'tok'
    state.user = { id: 1 }
    AuthModule.mutations.LOGOUT_SUCCESS(state)
    expect(state.accessToken).toBeNull()
    expect(state.user).toEqual({})
  })

  it('getter loggedIn reflects token presence', () => {
    const store = createStore({ modules: { Auth: AuthModule } })
    expect(store.getters['Auth/loggedIn']).toBe(false)
    store.commit('Auth/LOGIN_SUCCESS', 'abc')
    expect(store.getters['Auth/loggedIn']).toBe(true)
  })

  it('getter hasPermission checks name+slug pairs', () => {
    const store = createStore({ modules: { Auth: AuthModule } })
    store.commit('Auth/SET_USER', {
      permissions: [{ name: 'Manage users', slug: 'manage-users' }],
    })
    expect(store.getters['Auth/hasPermission'](['manage-users'], 'Manage users')).toBe(true)
    // одна строка вместо массива
    expect(store.getters['Auth/hasPermission']('manage-users', 'Manage users')).toBe(true)
    // нет совпадения по contentType
    expect(store.getters['Auth/hasPermission'](['manage-users'], 'Other')).toBe(false)
    // нет прав вообще
    expect(store.getters['Auth/hasPermission'](['nope'], 'Manage users')).toBe(false)
    // user без permissions -> false, не падает
    store.commit('Auth/SET_USER', {})
    expect(store.getters['Auth/hasPermission'](['x'], 'y')).toBe(false)
  })

  it('action login unwraps envelope, persists token/user and commits', async () => {
    loginApi.mockResolvedValue({
      data: {
        success: true,
        data: { token: 'tkn', user: { id: 7, fio: 'Petrov', permissions: [] } },
      },
    })
    const store = createStore({ modules: { Auth: AuthModule } })
    await store.dispatch('Auth/login', { fio: 'x', password: 'y' })
    expect(loginApi).toHaveBeenCalledWith({ fio: 'x', password: 'y' })
    expect(store.getters['Auth/loggedIn']).toBe(true)
    expect(store.state.Auth.user.id).toBe(7)
    expect(localStorage.getItem('token')).toBe('tkn')
    expect(JSON.parse(localStorage.getItem('user')).fio).toBe('Petrov')
  })

  it('action login supports legacy (non-envelope) response shape', async () => {
    loginApi.mockResolvedValue({ data: { token: 't2', user: { id: 1 } } })
    const store = createStore({ modules: { Auth: AuthModule } })
    await store.dispatch('Auth/login', {})
    expect(store.state.Auth.accessToken).toBe('t2')
  })

  it('action login commits LOGIN_ERROR on server error', async () => {
    loginApi.mockRejectedValue({
      response: { data: { errors: { email: ['wrong'] } } },
      message: 'Request failed',
    })
    const store = createStore({ modules: { Auth: AuthModule } })
    await expect(store.dispatch('Auth/login', {})).rejects.toBeTruthy()
    expect(store.state.Auth.errors).toEqual({ email: ['wrong'] })
  })

  it('action login handles network error without response', async () => {
    loginApi.mockRejectedValue(new Error('Network Error'))
    const store = createStore({ modules: { Auth: AuthModule } })
    await expect(store.dispatch('Auth/login', {})).rejects.toBeTruthy()
    expect(store.state.Auth.errors).toBe('Network Error')
  })

  it('action logout clears storage and state', async () => {
    localStorage.setItem('token', 'z')
    localStorage.setItem('user', '{"id":1}')
    const store = createStore({ modules: { Auth: AuthModule } })
    store.commit('Auth/LOGIN_SUCCESS', 'z')
    await store.dispatch('Auth/logout')
    expect(store.state.Auth.accessToken).toBeNull()
    expect(localStorage.getItem('token')).toBeNull()
    expect(localStorage.getItem('user')).toBeNull()
  })
})
