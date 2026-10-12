// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest'

// Навигация регистрируется в httpClient снаружи (onUnauthorizedHandler),
// а не импортом роутера: иначе возник бы цикл Router -> Store -> api.
// Поэтому и здесь перехватчик навигации ставится тем же способом.
const go = vi.fn()

vi.mock('../../resources/js/services/storage.service', () => ({
  TokenService: { removeToken: vi.fn(), getToken: vi.fn(() => 'token') },
}))
vi.mock('../../resources/js/services/user.service', () => ({
  UserService: { removeUser: vi.fn() },
}))
vi.mock('../../resources/js/services/language.service', () => ({
  LanguageService: { getLanguage: () => 'ru' },
}))
import { errorInterceptor, apiText, onUnauthorizedHandler } from '../../resources/js/api/httpClient'
import { TokenService } from '../../resources/js/services/storage.service'
import { toastItems, clear } from '../../resources/js/composables/useToast'

const err = (status, extra = {}) =>
  Object.assign(new Error('Request failed with status code ' + status), {
    response: { status, data: {} },
    ...extra,
  })

/** Ошибка запроса с конкретным URL — нужен для проверки исключений. */
const errFrom = (url, status = 401) =>
  Object.assign(new Error('Request failed with status code ' + status), {
    response: { status, data: {} },
    config: { url },
  })

describe('httpClient: перехватчик ошибок', () => {
  beforeEach(() => {
    clear()
    go.mockClear()
    TokenService.getToken.mockReturnValue('token')
    // removeToken — общий мок на весь файл: без сброса он помнит вызовы
    // из предыдущего теста, и проверка «не трогали токен» падала бы.
    TokenService.removeToken.mockClear()
    onUnauthorizedHandler(go)
  })

  const texts = () => toastItems.map((t) => t.text)

  it('403 объясняет причину и уводит на страницу отказа', () => {
    return errorInterceptor(err(403)).catch(() => {}).then(() => {
      expect(texts()).toContain('Недостаточно прав для этого действия')
      expect(go).toHaveBeenCalledWith('403')
    })
  })

  it('404 сообщает и уводит на страницу не найдено', () => {
    return errorInterceptor(err(404)).catch(() => {}).then(() => {
      expect(texts()).toContain('Запрошенные данные не найдены')
      expect(go).toHaveBeenCalledWith('404')
    })
  })

  it('401 закрывает сессию и предупреждает', () => {
    return errorInterceptor(err(401)).catch(() => {}).then(() => {
      expect(texts()).toContain('Сессия истекла, войдите заново')
      expect(go).toHaveBeenCalledWith('login')
    })
  })

  it('401 на самом входе — это «неверный пароль», а не «сессия истекла»', () => {
    // Форма входа разбирает код сама и показывает свой тост. Если
    // обработчик дополнительно скажет «сессия истекла» и уйдёт на
    // /login, пользователь увидит два противоречивых сообщения, а
    // форма сбивается с экрана прямо во время ввода.
    return errorInterceptor(errFrom('/api/login')).catch(() => {}).then(() => {
      expect(texts()).not.toContain('Сессия истекла, войдите заново')
      expect(go).not.toHaveBeenCalled()
      expect(TokenService.removeToken).not.toHaveBeenCalled()
    })
  })

  it('401 без токена не пугает «истёкшей сессией»', () => {
    // На публичных страницах 401 означает лишь «вы не авторизованы»:
    // закрывать нечего, а сообщение об истечении сессии вводит в заблуждение.
    TokenService.getToken.mockReturnValue(null)

    return errorInterceptor(errFrom('/api/notifications')).catch(() => {}).then(() => {
      expect(texts()).not.toContain('Сессия истекла, войдите заново')
      expect(go).not.toHaveBeenCalled()
    })
  })

  it('5xx показывает текст сервера, а не код ответа', () => {
    return errorInterceptor(err(500)).catch(() => {}).then(() => {
      expect(texts()).toContain('Ошибка сервера')
      // Раньше пользователь видел «Request failed with status code 500».
      expect(texts().join(' ')).not.toContain('status code')
    })
  })

  it('не показывает второй тост, если страница уже сообщила', () => {
    const e = err(403)

    return Promise.all([errorInterceptor(e).catch(() => {}), errorInterceptor(e).catch(() => {})])
      .then(() => {
        expect(toastItems.length).toBe(1)
      })
  })

  it('берёт текст из разных форм тела ответа', () => {
    expect(apiText('строка')).toBe('строка')
    expect(apiText({ error: 'ошибка' })).toBe('ошибка')
    expect(apiText({ error: { message: 'вложенно' } })).toBe('вложенно')
    expect(apiText({ data: { message: 'из data' } })).toBe('из data')
    expect(apiText(null)).toBe('')
  })

  it('optional-запрос остаётся тихим', () => {
    const e = err(500, { config: { optional: true } })

    return errorInterceptor(e).catch(() => {}).then(() => {
      expect(toastItems.length).toBe(0)
    })
  })
})
