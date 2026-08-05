import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { registerApi, loginApi, logoutApi, meApi, verifyTwoFactorApi } from '@/apis/auth.api'
import { getToken, setToken } from '@/apis/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value))
  const roles = computed(() => user.value?.roles ?? [])
  const permissions = computed(() => user.value?.permissions ?? [])
  const tenant = computed(() => user.value?.tenant ?? null)
  const isSuperAdmin = computed(() => Boolean(user.value?.is_super_admin))
  const plan = computed(() => tenant.value?.subscription?.plan ?? null)

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function hasPermission(permission) {
    return permissions.value.includes(permission)
  }

  function hasFeature(feature) {
    return Boolean(plan.value?.[feature])
  }

  async function register(payload) {
    const { data } = await registerApi(payload)
    applySession(data.data, true)
    return data
  }

  /**
   * When the account has two-factor enabled, the API responds with
   * `requires_two_factor` + a short-lived `two_factor_token` instead of a
   * session — no access_token exists yet, so applySession is skipped and
   * the caller (Login.vue) shows a code-entry step, then calls
   * verifyTwoFactor() to actually complete the login.
   */
  async function login(payload) {
    const { data } = await loginApi(payload)
    if (!data.data.requires_two_factor) {
      applySession(data.data, Boolean(payload.remember))
    }
    return data
  }

  async function verifyTwoFactor({ two_factor_token, code, remember = false }) {
    const { data } = await verifyTwoFactorApi({ two_factor_token, code })
    applySession(data.data, remember)
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

  function applySession(payload, remember = true) {
    setToken(payload.access_token, remember)
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
    isSuperAdmin,
    plan,
    hasRole,
    hasPermission,
    hasFeature,
    register,
    login,
    verifyTwoFactor,
    logout,
    fetchMe,
    initialize,
    clearSession,
  }
})
