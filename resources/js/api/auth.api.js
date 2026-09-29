import httpClient from './httpClient'
//console.log(httpClient, "httpClient")
const ENDPOINT = '/api/login'

const login = (formData) => httpClient.post(ENDPOINT, formData)
//console.log(login, 'login auth.api.js')

// Раньше выход был только локальным: AuthModule.logout чистил localStorage,
// но не удалял токен на сервере, поэтому он оставался валидным до истечения.
// Бэкенд отдаёт GET /api/logout (Route::get('/logout') в routes/api.php).
const logout = () => httpClient.get('/api/logout')

export { login, logout }
