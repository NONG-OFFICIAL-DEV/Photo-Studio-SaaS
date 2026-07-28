import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { registerApi, loginApi, logoutApi, meApi } from '@/apis/auth.api'
import { getToken, setToken } from '@/apis/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value))
  const roles = computed(() => user.value?.roles ?? [])
  const permissions = computed(() => user.value?.permissions ?? [])
  const tenant = computed(() => user.value?.tenant ?? null)

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function hasPermission(permission) {
    return permissions.value.includes(permission)
  }

  async function register(payload) {
    const { data } = await registerApi(payload)
    applySession(data.data)
    return data
  }

  async function login(payload) {
    const { data } = await loginApi(payload)
    applySession(data.data)
    return data
  }

  async function logout() {
    try {
      await logoutApi()
    } finally {
      clearSession()
    }
  }

  async function fetchMe() {
    try {
      const { data } = await meApi()
      user.value = data.data
    } catch {
      clearSession()
    } finally {
      initialized.value = true
    }
  }

  /**
   * Called once at app boot. If a token was persisted from a previous
   * session, validate it against the API before rendering protected pages.
   */
  async function initialize() {
    if (initialized.value) return
    if (getToken()) {
      await fetchMe()
    } else {
      initialized.value = true
    }
  }

  function applySession(payload) {
    setToken(payload.access_token)
    user.value = payload.user
    initialized.value = true
  }

  function clearSession() {
    setToken(null)
    user.value = null
  }

  return {
    user,
    initialized,
    isAuthenticated,
    roles,
    permissions,
    tenant,
    hasRole,
    hasPermission,
    register,
    login,
    logout,
    fetchMe,
    initialize,
    clearSession,
  }
})
