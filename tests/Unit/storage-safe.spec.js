// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest'

import { safeGetItem, safeSetItem, safeRemoveItem } from '../../resources/js/services/storage-safe'
import { TokenService } from '../../resources/js/services/storage.service'
import { UserService } from '../../resources/js/services/user.service'

/**
 * Регресс: localStorage бросает «Access is denied for this document»
 * на документах с непрозрачным origin (about:blank, sandbox, приватный
 * режим). Исключение всплывало из роутера и axios-интерцептора как
 * pageerror и ломало страницу.
 */
const makeStorageThrow = () => {
  const err = new Error("Failed to read the 'localStorage' property from 'Window': Access is denied for this document.")
  return {
    getItem: () => {
      throw err
    },
    setItem: () => {
      throw err
    },
    removeItem: () => {
      throw err
    },
  }
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('storage-safe: доступ к localStorage не бросает исключений', () => {
  it('getItem возвращает null вместо throw', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(() => safeGetItem('token')).not.toThrow()
    expect(safeGetItem('token')).toBeNull()
  })

  it('setItem и removeItem не бросают, а сообщают об отказе', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(safeSetItem('token', 'abc')).toBe(false)
    expect(safeRemoveItem('token')).toBe(false)
  })

  it('в обычном случае значения читаются и пишутся', () => {
    expect(safeSetItem('probe_key', 'value')).toBe(true)
    expect(safeGetItem('probe_key')).toBe('value')
    expect(safeRemoveItem('probe_key')).toBe(true)
    expect(safeGetItem('probe_key')).toBeNull()
  })
})

describe('сервисы не падают при недоступном хранилище', () => {
  it('TokenService.getToken() даёт null, а не исключение', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(() => TokenService.getToken()).not.toThrow()
    expect(TokenService.getToken()).toBeNull()
  })

  it('TokenService.saveToken/removeToken не бросают', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(() => TokenService.saveToken('abc')).not.toThrow()
    expect(() => TokenService.removeToken()).not.toThrow()
  })

  it('UserService.getUser() даёт null, а не исключение', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(() => UserService.getUser()).not.toThrow()
    expect(UserService.getUser()).toBeNull()
  })

  it('UserService.saveUser/removeUser не бросают', () => {
    vi.spyOn(window, 'localStorage', 'get').mockReturnValue(makeStorageThrow())
    expect(() => UserService.saveUser({ id: 1 })).not.toThrow()
    expect(() => UserService.removeUser()).not.toThrow()
  })
})
