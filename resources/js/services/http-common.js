import axios from 'axios'
import { safeGetItem } from './storage-safe';

// Put your backend url here
export const API_URL = `http://localhost:8000/api`

const $api = axios.create({
    withCredentials: true,
    baseURL: API_URL,
    timeout: 30000, 
})

$api.interceptors.request.use((config) => {    
    // Раньше Bearer подставлялся даже при null-токене и без защиты от
    // исключения доступа к localStorage.
    const token = safeGetItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config;
})


export default $api;