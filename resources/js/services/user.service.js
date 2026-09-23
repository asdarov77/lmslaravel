const USER_KEY = 'user'

/**
 * Manage the how user data is being stored and retrieved from storage.
 *
 * Current implementation stores to localStorage. Local Storage should always be
 * accessed through this instance.
 **/
const UserService = {
  getUser() {
    return localStorage.getItem(USER_KEY)
  },

  saveUser(user) {
    localStorage.setItem(USER_KEY, user)
    
  },

  removeUser() {
    localStorage.removeItem(USER_KEY)
    
  }
}

export { UserService }
