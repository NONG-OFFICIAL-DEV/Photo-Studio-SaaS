import axios from 'axios'
import { API_ENDPOINTS } from '@/constants/api-endpoints'

const TOKEN_KEY = 'photo_studio_access_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

/*
 * 401 handling: a single in-flight refresh call, with every other request
 * that fails meanwhile queued and replayed once the new token lands.
 * Avoids a refresh storm when several requests 401 at the same time.
 */
let isRefreshing = false
let pendingQueue = []

function resolveQueue(error, token = null) {
  pendingQueue.forEach(({ resolve, reject }) => (error ? reject(error) : resolve(token)))
  pendingQueue = []
}

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const { config, response } = error

    if (!response || response.status !== 401 || config?._retry || config?.url?.includes(API_ENDPOINTS.AUTH.REFRESH)) {
      return Promise.reject(error)
    }

    if (config.url?.includes(API_ENDPOINTS.AUTH.LOGIN)) {
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        pendingQueue.push({ resolve, reject })
      }).then((token) => {
        config.headers.Authorization = `Bearer ${token}`
        config._retry = true
        return http(config)
      })
    }

    config._retry = true
    isRefreshing = true

    try {
      const { data } = await http.post(API_ENDPOINTS.AUTH.REFRESH)
      const newToken = data.data.access_token
      setToken(newToken)
      resolveQueue(null, newToken)
      config.headers.Authorization = `Bearer ${newToken}`
      return http(config)
    } catch (refreshError) {
      resolveQueue(refreshError)
      setToken(null)
      window.dispatchEvent(new CustomEvent('auth:session-expired'))
      return Promise.reject(refreshError)
    } finally {
      isRefreshing = false
    }
  },
)
