import { describe, it, expect, vi, beforeEach } from 'vitest'

// Мокаем API-модули ДО импорта стора: экшены обязаны сами разворачивать конверт.
const api = vi.hoisted(() => ({
  fetchUsers: vi.fn(),
  fetchUser: vi.fn(),
  fetchGroups: vi.fn(),
  fetchGroup: vi.fn(),
  fetchPermissions: vi.fn(),
  createGroup: vi.fn(),
  updateGroup: vi.fn(),
  createUser: vi.fn(),
  updateUser: vi.fn(),
  deleteGroup: vi.fn(),
  deleteUser: vi.fn(),
  chpassUser: vi.fn(),
}))

vi.mock('../../resources/js/api/user.api', () => api)

import UserModule from '../../resources/js/Store/modules/UserModule'

// Так выглядит реальный ответ Laravel с middleware ApiResponseEnvelope
const envelope = (data, meta = null) => ({
  data: { success: true, data, error: null, meta },
})

beforeEach(() => {
  vi.clearAllMocks()
})

// Применяем НАСТОЯЩИЕ мутации модуля, иначе тест ничего не проверяет
const createContext = (state) => ({
  state,
  commit: (mutation, payload) => {
    if (UserModule.mutations[mutation]) {
      UserModule.mutations[mutation](state, payload)
    } else {
      throw new Error('Неизвестная мутация: ' + mutation)
    }
  },
  dispatch: () => {},
})

const runAction = async (name, payload, state) => {
  const context = createContext(state || UserModule.state())
  await UserModule.actions[name](context, payload)
  return { state: context.state }
}

describe('UserModule actions: разворачивание конверта', () => {
  it('fetchGroups кладёт в state массив, а не конверт', async () => {
    api.fetchGroups.mockResolvedValue(envelope([{ id: 1, name: 'Группа 1' }]))

    const state = UserModule.state()
    state.allGroups = []
    const { state: after } = await runAction('fetchGroups', undefined, state)

    expect(Array.isArray(after.allGroups)).toBe(true)
    expect(after.allGroups).toEqual([{ id: 1, name: 'Группа 1' }])
    // Регресс: раньше в state попадал { success, data, error, meta }
    expect(after.allGroups.success).toBeUndefined()
  })

  it('fetchUser кладёт в state объект пользователя', async () => {
    api.fetchUser.mockResolvedValue(envelope({ id: 37, fio: 'Иванов И.И.', role: 'Инструктор' }))

    const state = UserModule.state()
    state.user = null
    const { state: after } = await runAction('fetchUser', 37, state)

    expect(after.user).toEqual({ id: 37, fio: 'Иванов И.И.', role: 'Инструктор' })
    expect(after.user.success).toBeUndefined()
  })

  it('fetchPermissions кладёт в state массив', async () => {
    api.fetchPermissions.mockResolvedValue(envelope([{ id: 1, slug: 'manage-users' }]))

    const state = UserModule.state()
    state.allPermissions = []
    const { state: after } = await runAction('fetchPermissions', undefined, state)

    expect(Array.isArray(after.allPermissions)).toBe(true)
    expect(after.allPermissions[0].slug).toBe('manage-users')
  })

  it('fetchUsers корректно читает пагинацию из meta', async () => {
    api.fetchUsers.mockResolvedValue(
      envelope([{ id: 1 }, { id: 2 }], { pagination: { page: 1, perPage: 15, total: 42, totalPages: 3 } })
    )

    const state = UserModule.state()
    state.users = []
    const { state: after } = await runAction('fetchUsers', {}, state)

    expect(after.users).toHaveLength(2)
    expect(after.totalUsers).toBe(42)
    expect(after.pagination).toEqual({ page: 1, perPage: 15, total: 42, totalPages: 3 })
  })

  it('fetchUsers с пустым data не кладёт в state сам конверт', async () => {
    // Регресс: `response.data.data || response.data` при data === null
    // возвращал весь конверт, и .map в шаблоне падал.
    api.fetchUsers.mockResolvedValue(envelope(null))

    const state = UserModule.state()
    state.users = []
    const { state: after } = await runAction('fetchUsers', {}, state)

    expect(Array.isArray(after.users)).toBe(true)
    expect(after.users).toEqual([])
  })
})

describe('UserModule: мутации безопасны для шаблонов', () => {
  it('SET_ALL_GROUPS не падает, даже если пришёл не массив', () => {
    // Регресс-баг: "Cannot read properties of null (reading 'id')" в GroupList
    const state = UserModule.state()
    expect(() => UserModule.mutations.SET_ALL_GROUPS(state, null)).not.toThrow()
    expect(state.allGroups).toEqual([])

    expect(() => UserModule.mutations.SET_ALL_GROUPS(state, { success: true, data: [] })).not.toThrow()
    expect(state.allGroups).toEqual([])
  })

  it('SET_ALL_GROUPS сохраняет полученные данные', () => {
    const state = UserModule.state()
    UserModule.mutations.SET_ALL_GROUPS(state, [{ id: 3 }, { id: 1 }, { id: 2 }])
    expect(state.allGroups).toHaveLength(3)
    expect(state.allGroups[0].id).toBe(3)
  })

  it('SET_USER игнорирует мусор', () => {
    const state = UserModule.state()
    state.user = null
    expect(() => UserModule.mutations.SET_USER(state, undefined)).not.toThrow()
  })
})

describe('UserModule: updateUser откат при ошибке', () => {
  it('не бросает ReferenceError в catch (регресс "previous is not defined")', async () => {
    api.updateUser.mockRejectedValue(new Error('500 Internal Server Error'))

    const state = UserModule.state()
    state.users = [{ id: 5, fio: 'Старое Имя' }]
    const context = createContext(state)

    // Раньше `const previous` объявлялся внутри try, и catch к нему не обращался:
    // ReferenceError: previous is not defined маскировал исходную ошибку.
    await expect(
      UserModule.actions.updateUser(context, { id: 5, data: { fio: 'Новое Имя' } })
    ).rejects.toThrow('500 Internal Server Error')

    // Состояние должно откатиться к прежнему значению
    expect(state.users[0].fio).toBe('Старое Имя')
  })

  it('откатывается даже когда пользователя не было в списке', async () => {
    api.updateUser.mockRejectedValue(new Error('boom'))
    const state = UserModule.state()
    state.users = []
    const context = createContext(state)

    await expect(
      UserModule.actions.updateUser(context, { id: 999, data: { fio: 'X' } })
    ).rejects.toThrow('boom')
  })

  it('при успехе кладёт в users развёрнутый ответ', async () => {
    api.updateUser.mockResolvedValue(envelope({ id: 5, fio: 'Новое Имя' }))

    const state = UserModule.state()
    state.users = [{ id: 5, fio: 'Старое Имя' }]
    const context = createContext(state)

    await UserModule.actions.updateUser(context, { id: 5, data: { fio: 'Новое Имя' } })
    expect(state.users[0].fio).toBe('Новое Имя')
  })
})
