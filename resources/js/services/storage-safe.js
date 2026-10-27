/**
 * Безопасный доступ к Web Storage.
 *
 * localStorage бросает исключение («Access is denied for this document»)
 * не только в приватном режиме, но и на документах с непрозрачным origin
 * — about:blank, sandbox-фреймы, некоторые переходы навигации. Раньше
 * такое исключение всплывало как pageerror и роняло рендер и роутер.
 *
 * Здесь любое обращение оборачивается в try/catch и деградирует до
 * значения по умолчанию, чтобы проверка прав доступа не превращалась
 * в необработанную ошибку.
 */

export const safeGetItem = (key) => {
  try {
    return window.localStorage.getItem(key)
  } catch (e) {
    // Приватный режим / заблокированное хранилище / непрозрачный origin
    return null
  }
}

export const safeSetItem = (key, value) => {
  try {
    window.localStorage.setItem(key, value)
    return true
  } catch (e) {
    return false
  }
}

export const safeRemoveItem = (key) => {
  try {
    window.localStorage.removeItem(key)
    return true
  } catch (e) {
    return false
  }
}
