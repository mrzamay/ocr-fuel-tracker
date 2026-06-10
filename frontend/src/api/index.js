import axios from 'axios'
import { cacheApiResponse, getCachedApiResponse } from '../utils/db'

const api = axios.create({
  baseURL: '/api',
  timeout: 35000,
  withCredentials: true, // Важно для куки Sanctum
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  async response => {
    await cacheApiResponse(response.config, response.data)
    return response
  },
  async error => {
    const config = error.config || {}
    const isGet = (config.method || 'get').toLowerCase() === 'get'
    const isNetworkError = !error.response

    if (isGet && isNetworkError) {
      const cached = await getCachedApiResponse(config)
      if (cached) {
        return {
          data: cached.data,
          status: 200,
          statusText: 'Offline Cache',
          headers: {},
          config,
          request: error.request,
          offline: true
        }
      }
    }

    return Promise.reject(error)
  }
)

export default api
