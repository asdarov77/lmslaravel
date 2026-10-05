import { describe, it, expect, vi, beforeEach } from 'vitest'
import AuthModule from '../../resources/js/Store/modules/AuthModule'
import {
  canonicalRoleSlug,
  ROLE_ALIASES,
  roleSlugsFrom,
} from '../../resources/js/utils/roles'

const loginMock = vi.fn()
const logoutMock = vi.fn(() => Promise.resolve({ data: { success: true, data: null } }))
const fetchMeMock = vi.fn(() => Promise.resolve({ data: { success: true, data: null } }))
vi.mock('../../resources/js/api/auth.api', () => ({
  login: (...a) => loginMock(...a),
  logout: (...a) => logoutMock(...a),
  fetchMe: (...a) => fetchMeMock(...a),
}))

const getToken = vi.fn(() => null)
const saveToken = vi.fn()
const removeToken = vi.fn()
const saveUser = vi.fn()
const removeUser = vi.fn()
const getUser = vi.fn(() => null)
vi.mock('../../resources/js/services/storage.service', () => ({
  TokenService: {
    getToken: (...a) => getToken(...a),
    saveToken: (...a) => saveToken(...a),
    removeToken: (...a) => removeToken(...a),
  },
}))
vi.mock('../../resources/js/services/user.service', () => ({
  UserService: {
    getUser: (...a) => getUser(...a),
    saveUser: (...a) => saveUser(...a),
    removeUser: (...a) => removeUser(...a),
  },
}))

beforeEach(() => {
  vi.clearAllMocks()
  getToken.mockReturnValue(null)
  getUser.mockReturnValue(null)
})

const getters = (state) => {
  const g = {}
  for (const [name, fn] of Object.entries(AuthModule.getters)) {
    g[name] = typeof fn === 'function' ? fn(state, g, {}) : fn
  }
  return g
}

// ------------------------------------------------------- нормализация ролей

describe('canonicalRoleSlug', () => {
  it('приводит русские и английские написания к одному slug', () => {
    expect(canonicalRoleSlug('Обучаемый')).toBe('trainee')
    expect(canonicalRoleSlug('trainee')).toBe('trainee')
    expect(canonicalRoleSlug('Администратор')).toBe('admin')
    expect(canonicalRoleSlug('admin')).toBe('admin')
    expect(canonicalRoleSlug('Инструктор')).toBe('instructor')
  })

  it('нечувствителен к регистру и пробелам', () => {
    expect(canonicalRoleSlug('  обучаемый ')).toBe('trainee')
    expect(canonicalRoleSlug('ADMIN')).toBe('admin')
  })

  it('неизвестную роль сохраняет как есть', () => {
    // Раньше неопознанное значение исчезало, и UI получал пустое
    // состояние вместо понятного предупреждения.
    expect(canonicalRoleSlug('Методист')).toBe('Методист')
  })

  it('пустое значение даёт null, а не пустую строку', () => {
    expect(canonicalRoleSlug('')).toBeNull()
    expect(canonicalRoleSlug(null)).toBeNull()
    expect(canonicalRoleSlug(undefined)).toBeNull()
  })

  it('алиасы совпадают с бэкендовыми', () => {
    expect(Object.keys(ROLE_ALIASES).sort()).toEqual(['admin', 'instructor', 'trainee'])
  })
})

describe('roleSlugsFrom', () => {
  it('берёт роль из массива roles в формате бэкенда', () => {
    const roles = [{ id: 3, name: 'Обучаемый', slug: 'trainee' }]
    expect(roleSlugsFrom({}, roles)).toEqual(['trainee'])
  })

  it('принимает роли строками (legacy-формат login)', () => {
    expect(roleSlugsFrom({}, ['Обучаемый'])).toEqual(['trainee'])
  })

  it('учитывает роль только из role_user, которой нет в user.role', () => {
    // Ровно то, что возвращает /api/v1/me после chroll:
    // user.role пуст, а роль приходит в role_slugs.
    const user = { id: 1, role: '', role_slugs: ['instructor'] }
    expect(roleSlugsFrom(user, [{ id: 2, name: 'Инструктор', slug: 'instructor' }]))
      .toEqual(['instructor'])
  })

  it('не дублирует одну роль из разных источников', () => {
    const user = { role: 'Обучаемый', role_slugs: ['trainee'] }
    expect(roleSlugsFrom(user, [{ slug: 'trainee', name: 'Обучаемый' }])).toEqual(['trainee'])
  })

  it('пустой пользователь даёт пустой массив, а не ошибку', () => {
    expect(roleSlugsFrom(null)).toEqual([])
    expect(roleSlugsFrom({})).toEqual([])
    expect(roleSlugsFrom(undefined, 'не массив')).toEqual([])
  })
})

// ------------------------------------------------------------------ геттеры

describe('AuthModule: геттеры ролей', () => {
  it('isTrainee распознаёт и колонку, и роль из role_user', () => {
    const byColumn = getters({ ...AuthModule.state(), user: { role: 'Обучаемый' } })
    expect(byColumn.isTrainee).toBe(true)
    expect(byColumn.isAdmin).toBe(false)

    // Регресс: раньше сверка шла только по строке, поэтому роль из
    // role_user считалась отсутствующей.
    const byRole = getters({
      ...AuthModule.state(),
      user: { role: '', roles: [{ slug: 'trainee', name: 'Обучаемый' }] },
    })
    expect(byRole.isTrainee).toBe(true)
  })

  it('isAdmin и isInstructor не смешиваются', () => {
    const g = getters({ ...AuthModule.state(), user: { role: 'Инструктор' } })
    expect(g.isInstructor).toBe(true)
    expect(g.isAdmin).toBe(false)
    expect(g.isTrainee).toBe(false)
  })

  it('hasRole принимает несколько вариантов записи', () => {
    const g = getters({ ...AuthModule.state(), user: { role: 'admin' } })
    expect(g.hasRole('admin')).toBe(true)
    expect(g.hasRole('Администратор')).toBe(true)
    expect(g.hasRole('trainee')).toBe(false)
    expect(g.hasRole('admin', 'trainee')).toBe(true)
    expect(g.hasRole(['trainee'])).toBe(false)
  })

  it('hasRole без аргументов не блокирует (пункт меню без требований)', () => {
    const g = getters({ ...AuthModule.state(), user: { role: '' } })
    expect(g.hasRole()).toBe(true)
  })

  it('isSuperAdmin истинно для роли из role_user', () => {
    // Регресс: администратор, назначенный через UI, терял все права.
    const g = getters({
      ...AuthModule.state(),
      user: { role: '', roles: [{ slug: 'admin', name: 'Администратор' }] },
    })
    expect(g.isSuperAdmin).toBe(true)
  })

  it('isSuperAdmin по флагу бэкенда, даже если роли нет в ответе', () => {
    const g = getters({ ...AuthModule.state(), user: { is_super_admin: true } })
    expect(g.isSuperAdmin).toBe(true)
  })

  it('у пользователя без роли все ролевые геттеры ложны', () => {
    const g = getters({ ...AuthModule.state(), user: {} })
    expect(g.isAdmin).toBe(false)
    expect(g.isInstructor).toBe(false)
    expect(g.isTrainee).toBe(false)
    expect(g.isSuperAdmin).toBe(false)
  })
})

// ----------------------------------------------------- синхронизация со стейтом

describe('AuthModule: SET_ROLE_SLUGS', () => {
  it('нормализует значения при записи в стейт', () => {
    const state = AuthModule.state()
    AuthModule.mutations.SET_ROLE_SLUGS(state, ['Обучаемый', 'trainee', 'admin'])
    expect(state.roleSlugs).toEqual(['trainee', 'admin'])
  })

  it('не мусорит в стейт при некорректном значении', () => {
    const state = AuthModule.state()
    AuthModule.mutations.SET_ROLE_SLUGS(state, 'не массив')
    expect(state.roleSlugs).toEqual([])
  })

  it('logout чистит роли', () => {
    // Иначе после выхода геттер isAdmin оставался бы истинным из
    // прежнего пользователя.
    const state = AuthModule.state()
    state.accessToken = 't'
    state.user = { role: 'admin' }
    state.permissionSlugs = ['users.view']
    AuthModule.mutations.LOGOUT_SUCCESS(state)
    expect(state.roleSlugs).toEqual([])
    expect(state.accessToken).toBeNull()
  })
})

describe('AuthModule: fetchCurrentUser кладёт роли в стейт', () => {
  it('берёт role_slugs из ответа и сохраняет в user', async () => {
    getUser.mockReturnValue({ id: 1, fio: 'Ученик', role: '' })
    fetchMeMock.mockResolvedValue({
      data: {
        success: true,
        data: {
          user: { id: 1, fio: 'Ученик', role: '' },
          permission_slugs: ['courses.view'],
          roles: [{ id: 2, name: 'Обучаемый', slug: 'trainee' }],
          role_slugs: ['trainee'],
          permissions: [],
        },
      },
    })

    const commit = vi.fn((type, payload) => {
      if (type === 'SET_PERMISSIONS') AuthModule.mutations.SET_PERMISSIONS(state, payload)
      if (type === 'SET_ROLE_SLUGS') AuthModule.mutations.SET_ROLE_SLUGS(state, payload)
      if (type === 'SET_USER') AuthModule.mutations.SET_USER(state, payload)
    })
    const state = AuthModule.state()

    await AuthModule.actions.fetchCurrentUser({ commit, state })

    expect(state.roleSlugs).toEqual(['trainee'])
    expect(state.user.role_slugs).toEqual(['trainee'])
    expect(state.permissionSlugs).toEqual(['courses.view'])

    const g = getters(state)
    expect(g.isTrainee).toBe(true)
    expect(g.isAdmin).toBe(false)
  })

  it('падающий запрос не оставляет стейт в неконсистентном виде', async () => {
    fetchMeMock.mockRejectedValue(new Error('network down'))
    const state = AuthModule.state()
    const commit = vi.fn()

    await expect(
      AuthModule.actions.fetchCurrentUser({ commit, state })
    ).resolves.toBeNull()

    expect(commit).not.toHaveBeenCalledWith('SET_ROLE_SLUGS', expect.anything())
  })
})
