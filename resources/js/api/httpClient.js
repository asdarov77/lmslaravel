import axios from 'axios'
import { TokenService } from '../services/storage.service'

import { UserService } from '../services/user.service'

// Если VITE_APP_URL не задан (или пуст), axios использует относительные пути
// и запросы уходят на тот же origin, что и сама страница. Это делает сборку
// независимой от порта разработки/тестов и убирает CORS между 8000 и 8080.
const appUrl = import.meta.env.VITE_APP_URL
const httpClient = axios.create({
  baseURL: appUrl || '',  
  timeout: 60000, // indicates, 60000ms ie. 30 seconds
  headers: {
    'Content-Type': 'application/json'
  }
})

/**
 * Навигация регистрируется снаружи через onUnauthorizedHandler().
 *
 * Раньше здесь был `import router from '../Router'`, что создавало цикл:
 * httpClient -> Router -> Store -> модули стора -> *.api -> httpClient.
 * В итоге при загрузке Store/index.js модуль оказывался undefined
 * ("Cannot read properties of undefined (reading 'getters')") и падали
 * все юнит-тесты, импортирующие любой модуль стора.
 * API-слой не должен знать о роутере — направление зависимости должно
 * идти от UI к инфраструктуре.
 */
let navigate = null
export const onUnauthorizedHandler = handler => {
  navigate = handler
}

const go = name => {
  if (typeof navigate === 'function') {
    Promise.resolve(navigate(name)).catch(err => err)
  }
}
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
  // Сетевые сбои/CORS приходят без error.response — раньше это роняло
  // интерцептор с TypeError на первом же обращении к error.response.status.
  const status = error.response?.status
  const message = error.response?.data ?? error.message

  if (!status) {
    console.error('Network error:', error.message)
    return Promise.reject(error)
  }

  // Необязательные ресурсы (например, статические файлы курса
  // api/private/... , которых может не быть на диске) запрашиваются с
  // { optional: true }. Для них 404 — это ожидаемый ответ, который
  // обрабатывает сам вызывающий код. Раньше общий обработчик и писал
  // ошибку в консоль, и УВОДИЛ пользователя на страницу 404, хотя
  // содержимое курса просто не развёрнуто.
  if (error.config && error.config.optional) {
    return Promise.reject(error)
  }

  switch (status) {
    case 400:
      console.error(status, error.message)
      break

    case 401: // authentication error, logout the user
      TokenService.removeToken()
      UserService.removeUser()
      go('login')
      break

    case 403: // прав недостаточно — бэкенд остался источником истины
      go('403')
      break

    case 404: // not found
      console.error(status, error.message)
      go('404')
      break

    case 500: // service unavailable
      console.error(status, error.message)
      if (typeof message === 'string' && message.includes('ECONNREFUSED')) go('500')
      break

    case 502: // internal server error
    case 503: // bad gateway
      console.error(status, error.message)
      go('500')
      break

    default:
      console.error(status, error.message)
  }
  return Promise.reject(error)
}

 // Interceptor for responses
 const responseInterceptor = response => {
   return response
 }

// Включён: раньше был закомментирован, поэтому 401/403 обрабатывались
// только в местах вызова (или не обрабатывались вовсе).
httpClient.interceptors.response.use(responseInterceptor, errorInterceptor)

export default httpClient
