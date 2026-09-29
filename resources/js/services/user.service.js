import { safeGetItem, safeSetItem, safeRemoveItem } from './storage-safe'

const USER_KEY = 'user'

const UserService = {
  getUser() {
    const userStr = safeGetItem(USER_KEY)
    if (!userStr || userStr === 'undefined') return null
    try {
      return JSON.parse(userStr)
    } catch (e) {
      console.error('Ошибка парсинга user из LocalStorage:', e)
      safeRemoveItem(USER_KEY)
      return null
    }
  },
  saveUser(user) {
    if (user) safeSetItem(USER_KEY, JSON.stringify(user))
  },
  removeUser() {
    safeRemoveItem(USER_KEY)
  }
}

export { UserService, USER_KEY }