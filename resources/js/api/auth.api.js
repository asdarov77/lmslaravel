import httpClient from './httpClient'
//console.log(httpClient, "httpClient")
const ENDPOINT = '/api/login'

const login = (formData) => httpClient.post(ENDPOINT, formData)
//console.log(login, 'login auth.api.js')

export { login }
