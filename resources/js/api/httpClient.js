import axios from 'axios'
//import router from '../Router';
import { TokenService } from '../services/storage.service'

import { UserService } from '../services/user.service'
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
  // all the error responses
  //console.log(error.response.data)
  switch (error.response.status) {
    case 400:
      console.error(error.response.status, error.message)
      break

    case 401: // authentication error, logout the user
      TokenService.removeToken()
      UserService.removeUser()
      router.push({ name: 'login' }).catch(err => err)
      break

    case 404: // not found
      console.error(error.response.status, error.message)
      router.push({ name: '404' }).catch(err => err)
      break

    case 500: // service unavailable
      console.error(error.response.status, error.message)
      if (error.response.data.includes('ECONNREFUSED')) router.push({ name: '500' }).catch(err => err)
      break

    case 502: // internal server error
    case 503: // bad gateway
      console.error(error.response.status, error.message)
      router.push({ name: '500' }).catch(err => err)
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

// httpClient.interceptors.response.use(responseInterceptor, errorInterceptor)

export default httpClient
