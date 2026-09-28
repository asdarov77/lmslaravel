import axios from 'axios'
import { TokenService } from '../services/storage.service'

import { UserService } from '../services/user.service'

// Ленивая загрузка роутера разрывает циклическую зависимость:
// Store -> Modules -> api -> httpClient -> Router -> routes -> Store.
// Без ленивости при импорте стора напрямую Router ещё не инициализирован,
// и обращение к store.getters падает с "Cannot read properties of undefined".
let routerRef = null
const getRouter = () => {
  if (routerRef) return routerRef
  try {
    // синхронный require невозможен в ESM; используем fire-and-forget промис:
    // первый импорт запустит модуль, последующие редиректы уже получат роутер
    import('../Router').then((m) => { routerRef = m.default })
      .catch(() => { /* в тестовой среде роутер может отсутствовать */ })
  } catch (e) {
    /* noop */
  }
  return routerRef
}
const safePush = (target) => {
  const r = getRouter()
  if (r && typeof r.push === 'function') r.push(target).catch((err) => err)
}
const httpClient = axios.create({
  baseURL: `${import.meta.env.VITE_APP_URL}`,  
  timeout: 60000, // indicates, 60000ms ie. 30 seconds
  headers: {
    'Content-Type': 'application/json'
  }
})
const getAuthToken = () => TokenService.getToken()
//console.log(getAuthToken, "getAuthToken")
const authInterceptor = config => {
  const token = getAuthToken()
  //console.log(token, "getAuthToken")
  if (token) {
    //config.headers['Authorization'] = `Token ${token}`
    config.headers['Authorization'] = `Bearer ${token}`
  }
  return config
}
//console.log(authInterceptor, "authInterceptor")
httpClient.interceptors.request.use(authInterceptor)

//console.log(baseURL, "baseURL");
// interceptor to catch errors
const errorInterceptor = error => {
  // сетевые сбои / таймауты: у ошибки нет response — не падаем, просто отклоняем промис
  if (!error.response) {
    console.error('Network error:', error.message)
    return Promise.reject(error)
  }

  // all the error responses
  switch (error.response.status) {
    case 400:
      console.error(error.response.status, error.message)
      break

    case 401: // authentication error, logout the user
      TokenService.removeToken()
      UserService.removeUser()
      safePush({ name: 'login' })
      break

    case 404: // not found
      console.error(error.response.status, error.message)
      safePush({ name: '404' })
      break

    case 500: // service unavailable
      console.error(error.response.status, error.message)
      safePush({ name: '500' })
      break

    case 502: // bad gateway
    case 503: // internal server error / service unavailable
      console.error(error.response.status, error.message)
      safePush({ name: '500' })
      break

    default:
      console.error(error.response.status, error.message)
  }
  return Promise.reject(error)
}

// Interceptor for responses
const responseInterceptor = response => {
  return response
}

httpClient.interceptors.response.use(responseInterceptor, errorInterceptor)

export default httpClient
