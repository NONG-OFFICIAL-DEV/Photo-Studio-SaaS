import axios from 'axios'

const TOKEN_KEY = 'photo_studio_access_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY)
}

/**
 * remember=true persists the token across browser restarts (localStorage);
 * remember=false keeps it only for the current tab session (sessionStorage).
 * When remember is omitted (e.g. the silent refresh flow), the token is
 * re-saved to whichever storage already holds it, so a refreshed token
 * doesn't get "upgraded" to persistent storage on every background refresh.
 */
export function setToken(token, remember) {
  if (!token) {
    localStorage.removeItem(TOKEN_KEY)
    sessionStorage.removeItem(TOKEN_KEY)
    return
  }

  if (remember === undefined) {
    remember = Boolean(localStorage.getItem(TOKEN_KEY))
  }

  const primary = remember ? localStorage : sessionStorage
  const secondary = remember ? sessionStorage : localStorage
  primary.setItem(TOKEN_KEY, token)
  secondary.removeItem(TOKEN_KEY)
}

const http = axios.create({
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

/*
 * Account/subscription gate handling: IdentifyTenant and
 * EnsureSubscriptionActive block every tenant-scoped route (403/402) once a
 * tenant is suspended or its trial/subscription has lapsed, returning one
 * of these stable codes. `/v1/auth/*` and `/v1/billing/*` are deliberately
 * exempt from one or both gates (so a blocked user can still log in, check
 * `/me`, and reach billing to fix a lapsed *subscription* — though not a
 * platform *suspension*, which blocks billing too) — so a request to those
 * always-reachable routes succeeding is NOT evidence the block is lifted.
 * Only a genuinely gated route succeeding is a reliable "all clear" signal.
 */
const ACCOUNT_BLOCKED_CODES = [
  'NO_SUBSCRIPTION_FOUND',
  'SUBSCRIPTION_STATUS_BLOCKED',
  'TRIAL_ENDED',
  'SUBSCRIPTION_EXPIRED',
  'TENANT_SUSPENDED',
  'NO_TENANT_ASSOCIATED',
]
const GATE_EXEMPT_URL_PREFIXES = ['/v1/auth/', '/v1/billing/']

function isGateExempt(url) {
  return GATE_EXEMPT_URL_PREFIXES.some((prefix) => url?.includes(prefix))
}

http.interceptors.response.use(
  (response) => {
    if (!isGateExempt(response.config?.url)) {
      window.dispatchEvent(new CustomEvent('subscription:blocked', { detail: null }))
    }
    return response
  },
  async (error) => {
    const { config, response } = error

    if (response && ACCOUNT_BLOCKED_CODES.includes(response.data?.code)) {
      window.dispatchEvent(new CustomEvent('subscription:blocked', { detail: response.data }))
    }

    if (!response || response.status !== 401 || config?._retry || config?.url?.includes('/v1/auth/refresh')) {
      return Promise.reject(error)
    }

    if (config.url?.includes('/v1/auth/login')) {
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
      const { data } = await http.post('/v1/auth/refresh')
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

export default http
