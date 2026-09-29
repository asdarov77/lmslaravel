import { safeGetItem, safeSetItem, safeRemoveItem } from './storage-safe'

const TOKEN_KEY = 'token'

/**
 * Manage the how Access Tokens are being stored and retrieved from storage.
 *
 * Current implementation stores to localStorage. Local Storage should always be
 * accessed through this instance.
 **/
const TokenService = {
  getToken() {
    const token = safeGetItem(TOKEN_KEY)
    // защита от ранее записанной строки "undefined"
    return token && token !== 'undefined' ? token : null
  },

  saveToken(accessToken) {
    safeSetItem(TOKEN_KEY, accessToken)
    //console.log(accessToken,'accessToken')
  },

  removeToken() {
    safeRemoveItem(TOKEN_KEY)
  }
}

export { TokenService }
