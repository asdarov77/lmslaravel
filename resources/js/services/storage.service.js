const TOKEN_KEY = 'token'


/**
 * Manage the how Access Tokens are being stored and retrieved from storage.
 *
 * Current implementation stores to localStorage. Local Storage should always be
 * accessed through this instance.
 **/
const TokenService = {
  getToken() {
    return localStorage.getItem(TOKEN_KEY)
  },

  saveToken(accessToken) {
    localStorage.setItem(TOKEN_KEY, accessToken)
    //console.log(accessToken,'accessToken')
  },

  removeToken() {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export { TokenService }
