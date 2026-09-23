const USER_KEY = 'user'

const UserService = {
  getUser() {
    const userStr = localStorage.getItem(USER_KEY)
    if (!userStr || userStr === 'undefined') return null
    try {
      return JSON.parse(userStr)
    } catch (e) {
      console.error('Ошибка парсинга user из LocalStorage:', e)
      localStorage.removeItem(USER_KEY)
      return null
    }
  },
  saveUser(user) {
    if (user) localStorage.setItem(USER_KEY, JSON.stringify(user))
  },
  removeUser() {
    localStorage.removeItem(USER_KEY)
  }
}

export { UserService, USER_KEY }