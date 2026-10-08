import axios from 'axios'
import { TokenService } from '../services/storage.service'

import { UserService } from '../services/user.service'
import { LanguageService } from '../services/language.service'
import { toast } from '../composables/useToast'
import ru from '../locales/ru.json'
import en from '../locales/en.json'

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
const authInterceptor = config => {
  const token = getAuthToken()
  if (token) {
    //config.headers['Authorization'] = `Token ${token}`
    config.headers['Authorization'] = `Bearer ${token}`
  }
  return config
}
httpClient.interceptors.request.use(authInterceptor)

// interceptor to catch errors
const errorInterceptor = error => {
  // Сетевые сбои/CORS приходят без error.response — раньше это роняло
  // интерцептор с TypeError на первом же обращении к error.response.status.
  const status = error.response?.status
  const message = error.response?.data ?? error.message

  if (!status) {
    console.error('Network error:', error.message)

    /*
     * Сетевая ошибка — это не «страница не найдена», а «сервер не
     * отвечает». Раньше такой сбой просто отклонял промис, и
     * пользователь видел пустой экран без объяснений.
     *
     * Страницу 500 не открываем: она про ошибку СЕРВЕРА, а здесь
     * сервер вообще недоступен. Достаточно сообщения.
     */
    report('error', t('http.networkError'))

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

  /*
   * Показываем отказ всегда, даже если уводим на страницу ошибки.
   *
   * Раньше 403 и 404 просто делали go('403')/go('404') без объяснения:
   * пользователь видел «в доступе отказано» и не знал, что именно
   * запрос не прошёл. Теперь причина остаётся на экране.
   *
   * Дубли не глушим: если страница уже показала своё сообщение об этой
   * же ошибке, второй тост был бы шумом. Отметка ставится на самом
   * объекте ошибки, а не в модуле, — иначе она пережила бы запрос и
   * подавила следующее настоящее сообщение.
   */
  const report = (type, text) => {
    if (error.__toastShown) return

    error.__toastShown = true
    toast[type](text)
  }

  switch (status) {
    case 400:
      console.error(status, error.message)
      report('error', apiText(message) || apiText(error.response) || error.message)
      break

    case 401: // authentication error, logout the user
      TokenService.removeToken()
      UserService.removeUser()
      report('warning', t('http.sessionExpired'))
      go('login')
      break

    case 403: // прав недостаточно — бэкенд остался источником истины
      report('error', t('http.forbidden'))
      go('403')
      break

    case 404: // not found
      console.error(status, error.message)
      report('error', t('http.notFound'))
      go('404')
      break

    case 500: // service unavailable
      console.error(status, error.message)
      report('error', t('http.serverError'))
      if (typeof message === 'string' && message.includes('ECONNREFUSED')) go('500')
      break

    case 502: // internal server error
    case 503: // bad gateway
      console.error(status, error.message)
      report('error', t('http.unavailable'))
      go('500')
      break

    default:
      console.error(status, error.message)
      report('error', apiText(message) || error.message)
  }
  return Promise.reject(error)
}

 // Interceptor for responses
 const responseInterceptor = response => {
   return response
 }

// Включён: раньше был закомментирован, поэтому 401/403 обрабатывались
// только в местах вызова (или не обрабатывались вовсе).
/*
 * Текст ошибки из ответа API.
 *
 * Конверт ответа неоднороден: в нём бывает {error: 'текст'},
 * {error: {message}}, {data: {message}} и просто строка. Раньше каждая
 * страница разбирала это по-своему и чаще всего показывала
 * `error.message` — то есть 'Request failed with status code 500',
 * что пользователю ничего не объясняет.
 */
const apiText = (payload) => {
  if (!payload) return ''
  if (typeof payload === 'string') return payload.trim()
  return payload.error?.message
    || payload.error
    || payload.data?.message
    || payload.message
    || ''
}

/*
 * Переводы для служебных сообщений.
 *
 * Берёмся напрямую из файла локалей, а не через this.$t: перехватчик
 * живёт вне компонента Vue, и i18n-инстанс там недоступен.
 *
 * Язык читается из LanguageService, а НЕ из стора: импорт стора здесь
 * образовал бы цикл Store -> модули -> api -> httpClient. Прямо на
 * верху файла об этом написано, поэтому и язык берём мимо стора.
 */
const t = (key) => {
  const locale = LanguageService.getLanguage() === 'en' ? en : ru

  return locale?.[key.split('.')[0]]?.[key.split('.')[1]] || key
}

httpClient.interceptors.response.use(responseInterceptor, errorInterceptor)

/*
 * Экспорт для тестов.
 *
 * Сам перехватчик проверяется юнит-тестом: он решает, покажет ли
 * приложение причину отказа, и это нельзя проверить через интерфейс —
 * ни одна страница не делает «намеренно падающий» запрос. Наружу
 * отдаётся только обработчик, сам клиент остаётся прежним.
 */
export { errorInterceptor, apiText }

export default httpClient
