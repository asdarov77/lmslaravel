import { describe, it, expect, vi, beforeEach } from 'vitest'
import AuthModule from '../../resources/js/Store/modules/AuthModule'
import UiModule from '../../resources/js/Store/modules/UiModule'
import UserPageModule from '../../resources/js/Store/modules/userPage.store'

const loginMock = vi.fn()
const logoutMock = vi.fn(() => Promise.resolve({ data: { success: true, data: null } }))
vi.mock('../../resources/js/api/auth.api', () => ({
  login: (...a) => loginMock(...a),
  logout: (...a) => logoutMock(...a),
}))

const saveToken = vi.fn()
const removeToken = vi.fn()
const getToken = vi.fn(() => null)
const saveUser = vi.fn()
const removeUser = vi.fn()
const getUser = vi.fn(() => null)

vi.mock('../../resources/js/services/storage.service', () => ({
  TokenService: {
    saveToken: (...a) => saveToken(...a),
    removeToken: (...a) => removeToken(...a),
    getToken: (...a) => getToken(...a),
  },
}))
vi.mock('../../resources/js/services/user.service', () => ({
  UserService: {
    saveUser: (...a) => saveUser(...a),
    removeUser: (...a) => removeUser(...a),
    getUser: (...a) => getUser(...a),
  },
}))

const getLanguage = vi.fn(() => 'ru')
vi.mock('../../resources/js/services/language.service', () => ({
  LanguageService: { getLanguage: (...a) => getLanguage(...a) },
}))

const freshState = () => AuthModule.state()

beforeEach(() => {
  vi.clearAllMocks()
  getToken.mockReturnValue(null)
  getUser.mockReturnValue(null)
  getLanguage.mockReturnValue('ru')
})

// ------------------------------------------------------------------ AUTH

describe('AuthModule: state', () => {
  it('объявляет все поля реактивными', () => {
    const s = freshState()
    expect(s).toHaveProperty('accessToken')
    expect(s).toHaveProperty('user')
    expect(s).toHaveProperty('errors')
    expect(s).toHaveProperty('language')
  })

  it('берёт токен и пользователя из хранилища при старте', () => {
    getToken.mockReturnValue('stored-token')
    getUser.mockReturnValue({ id: 5, fio: 'Из Хранилища' })

    const s = AuthModule.state()
    expect(s.accessToken).toBe('stored-token')
    expect(s.user).toEqual({ id: 5, fio: 'Из Хранилища' })
  })

  it('не падает, если хранилище повреждено', () => {
    getUser.mockImplementation(() => {
      throw new Error('битый JSON')
    })
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
    expect(() => AuthModule.state()).not.toThrow()
    expect(AuthModule.state().user).toEqual({})
    spy.mockRestore()
  })
})

describe('AuthModule: mutations', () => {
  it('LOGIN_SUCCESS сохраняет токен и сбрасывает ошибки', () => {
    const s = freshState()
    s.errors = { fio: 'была ошибка' }
    AuthModule.mutations.LOGIN_SUCCESS(s, 'new-token')
    expect(s.accessToken).toBe('new-token')
    expect(s.errors).toBeNull()
  })

  it('LOGIN_ERROR записывает ошибки, не трогая токен', () => {
    const s = freshState()
    AuthModule.mutations.LOGIN_SUCCESS(s, 'keep-me')
    AuthModule.mutations.LOGIN_ERROR(s, { fio: 'required' })
    expect(s.errors).toEqual({ fio: 'required' })
    expect(s.accessToken).toBe('keep-me')
  })

  it('LOGOUT_SUCCESS очищает токен, пользователя и ошибки', () => {
    const s = freshState()
    s.accessToken = 'tok'
    s.user = { id: 1 }
    s.errors = { x: 1 }
    AuthModule.mutations.LOGOUT_SUCCESS(s)
    expect(s.accessToken).toBeNull()
    expect(s.user).toEqual({})
    expect(s.errors).toBeNull()
  })

  it('SET_USER заменяет пользователя', () => {
    const s = freshState()
    AuthModule.mutations.SET_USER(s, { id: 9, fio: 'Новый' })
    expect(s.user).toEqual({ id: 9, fio: 'Новый' })
  })

  it('SET_LANGUAGE меняет язык', () => {
    const s = freshState()
    AuthModule.mutations.SET_LANGUAGE(s, 'en')
    expect(s.language).toBe('en')
  })
})

describe('AuthModule: login action', () => {
  const envelope = payload => ({ data: { success: true, data: payload, error: null, meta: null } })

  it('разворачивает конверт и сохраняет токен с пользователем', async () => {
    loginMock.mockResolvedValue(envelope({ token: 'tok-1', user: { id: 7, fio: 'Иван' }, permissions: [] }))

    const commit = vi.fn()
    const s = freshState()
    await await AuthModule.actions.login({ commit }, { fio: 'Иван', password: 'secret' })

    expect(saveToken).toHaveBeenCalledWith('tok-1')
    expect(saveUser).toHaveBeenCalledWith({ id: 7, fio: 'Иван' })
    expect(commit).toHaveBeenCalledWith('LOGIN_SUCCESS', 'tok-1')
    expect(commit).toHaveBeenCalledWith('SET_USER', { id: 7, fio: 'Иван' })
  })

  it('работает и без конверта (голый payload)', async () => {
    loginMock.mockResolvedValue({ data: { token: 'tok-2', user: { id: 8 } } })
    const commit = vi.fn()
    await AuthModule.actions.login({ commit }, { fio: 'X', password: 'y' })
    expect(saveToken).toHaveBeenCalledWith('tok-2')
  })

  it('извлекает ошибки валидации из конверта', async () => {
    loginMock.mockRejectedValue({ response: { data: { errors: { fio: 'required' } } } })
    const commit = vi.fn()
    await expect(
      AuthModule.actions.login({ commit }, {})
    ).rejects.toBeTruthy()
    expect(commit).toHaveBeenCalledWith('LOGIN_ERROR', { fio: 'required' })
  })

  it('не падает при сетевой ошибке без response', async () => {
    loginMock.mockRejectedValue({ message: 'Network Error' })
    const commit = vi.fn()
    await expect(AuthModule.actions.login({ commit }, {})).rejects.toBeTruthy()
    expect(commit).toHaveBeenCalledWith('LOGIN_ERROR', 'Network Error')
  })

  it('подставляет дефолтный текст при ошибке без message и response', async () => {
    loginMock.mockRejectedValue({})
    const commit = vi.fn()
    await expect(AuthModule.actions.login({ commit }, {})).rejects.toBeTruthy()
    expect(commit).toHaveBeenCalledWith('LOGIN_ERROR', 'Произошла ошибка сети')
  })

  it('не сохраняет токен при неудачном логине', async () => {
    loginMock.mockRejectedValue({ message: 'bad' })
    const commit = vi.fn()
    await expect(AuthModule.actions.login({ commit }, {})).rejects.toBeTruthy()
    expect(saveToken).not.toHaveBeenCalled()
    expect(saveUser).not.toHaveBeenCalled()
  })
})

describe('AuthModule: logout action', () => {
  it('чистит хранилище и состояние', async () => {
    const commit = vi.fn()
    // logout теперь асинхронный: сначала инвалидирует токен на сервере,
    // затем чистит локальное состояние
    await AuthModule.actions.logout({ commit })
    expect(removeUser).toHaveBeenCalled()
    expect(removeToken).toHaveBeenCalled()
    expect(commit).toHaveBeenCalledWith('LOGOUT_SUCCESS')
  })
})

// -------------------------------------------------------------------- UI

describe('UiModule', () => {
  it('читает язык из сервиса', () => {
    getLanguage.mockReturnValue('en')
    expect(UiModule.state().language).toBe('en')
  })

  it('меню открыто после логина и закрыто после выхода', () => {
    const s = UiModule.state()
    expect(s.menudrawler).toBe(false)

    UiModule.actions.login({ commit: vi.fn() })
    UiModule.mutations.LOGIN_SUCCESS(s)
    expect(s.menudrawler).toBe(true)

    UiModule.mutations.LOGOUT_SUCCESS(s)
    expect(s.menudrawler).toBe(false)
  })

  it('login/logout actions коммитят правильные мутации', () => {
    const commit = vi.fn()
    UiModule.actions.login({ commit })
    expect(commit).toHaveBeenCalledWith('LOGIN_SUCCESS')
    commit.mockClear()
    UiModule.actions.logout({ commit })
    expect(commit).toHaveBeenCalledWith('LOGOUT_SUCCESS')
  })

  it('SET_LANGUAGE меняет язык', () => {
    const s = UiModule.state()
    UiModule.mutations.SET_LANGUAGE(s, 'de')
    expect(s.language).toBe('de')
  })
})

// ------------------------------------------------------------- USER PAGE

describe('userPage.store (моковые данные страницы курса)', () => {
  it('экспортирует namespaced-модуль с начальным состоянием', () => {
    expect(UserPageModule.namespaced).toBe(true)
    expect(UserPageModule.state).toBeTypeOf('object')
  })

  it('содержит два курса с темами и главами', () => {
    const s = UserPageModule.state
    expect(s.courses).toHaveLength(2)
    expect(s.courses[0].topics.length).toBeGreaterThan(0)
    expect(s.courses[0].topics[0].chapters.length).toBeGreaterThan(0)
    expect(s.courses[0].className).toBeTruthy()
  })

  it('главы содержат часы, прогресс и даты', () => {
    const ch = UserPageModule.state.courses[0].topics[0].chapters[0]
    expect(ch).toHaveProperty('countHours')
    expect(ch).toHaveProperty('progress')
    expect(ch.dateChapterStart).toBeInstanceOf(Date)
    expect(ch.dateChapterStop).toBeInstanceOf(Date)
  })

  it('тесты и результаты присутствуют', () => {
    const s = UserPageModule.state
    expect(s.testsCourse.length).toBeGreaterThan(0)
    expect(s.testScores.length).toBeGreaterThan(0)
    expect(s.testsCourse[0].dateStart).toBeInstanceOf(Date)
  })
})

// ---------------------------------------------------- РЕГРЕСС: logout в API

describe('AuthModule.logout инвалидирует токен на сервере', () => {
  it('обращается к бэкенду, а не только чистит localStorage', async () => {
    getToken.mockReturnValue('token-123')
    await AuthModule.actions.logout({ commit: () => {} })

    expect(logoutMock, 'logout обязан дёрнуть API').toHaveBeenCalledTimes(1)
    expect(removeToken, 'локальный токен удаляется').toHaveBeenCalled()
    expect(removeUser, 'локальный пользователь удаляется').toHaveBeenCalled()
  })

  it('чистит локальное состояние даже если API вернул ошибку', async () => {
    logoutMock.mockRejectedValueOnce(new Error('network down'))
    await AuthModule.actions.logout({ commit: () => {} })

    expect(removeToken, 'ошибка сети не должна мешать выйти').toHaveBeenCalled()
    expect(removeUser).toHaveBeenCalled()
  })

  it('коммитит LOGOUT_SUCCESS', async () => {
    const commit = vi.fn()
    await AuthModule.actions.logout({ commit })
    expect(commit).toHaveBeenCalledWith('LOGOUT_SUCCESS')
  })
})
