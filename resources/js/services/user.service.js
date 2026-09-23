const UserService = {
  getUser() {
    const userStr = localStorage.getItem(USER_KEY)
    // Возвращаем null если значение отсутствует или равно "undefined"
    if (!userStr || userStr === 'undefined') {
      return null
    }
    try {
      return JSON.parse(userStr)
    } catch (e) {
      console.error('Ошибка парсинга user из LocalStorage:', e)
      return null
    }
  },

  saveUser(user) {
    if (user) {
      localStorage.setItem(USER_KEY, JSON.stringify(user))
    }
  },

  removeUser() {
    localStorage.removeItem(USER_KEY)
  }
}

export { UserService }
