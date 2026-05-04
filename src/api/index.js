import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
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

export default api
