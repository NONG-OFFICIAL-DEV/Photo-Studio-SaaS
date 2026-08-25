import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { registerApi, loginApi, logoutApi, meApi, verifyTwoFactorApi, googleAuthApi, googleRegisterApi } from '@/apis/auth.api'
import { getToken, setToken } from '@/apis/api'
import { connectEcho, disconnectEcho } from '@/plugins/echo'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => Boolean(user.value))
  const roles = computed(() => user.value?.roles ?? [])
  const permissions = computed(() => user.value?.permissions ?? [])
  const tenant = computed(() => user.value?.tenant ?? null)
  const isSuperAdmin = computed(() => Boolean(user.value?.is_super_admin))
  const plan = computed(() => tenant.value?.subscription?.plan ?? null)
  const subscription = computed(() => tenant.value?.subscription ?? null)

  // Picks the date that actually determines when access lapses for the
  // subscription's current status — mirrors SubscriptionService's own
  // trial_ends_at vs current_period_ends_at branching. null for any other
  // status (already unusable, or no relevant date to warn about).
  const subscriptionDaysLeft = computed(() => {
    const sub = subscription.value
    if (!sub) return null

    const endsAt = sub.status === 'trial' ? sub.trial_ends_at : sub.status === 'active' ? sub.current_period_ends_at : null
    if (!endsAt) return null

    const diffMs = new Date(endsAt) - new Date()
    return Math.max(0, Math.ceil(diffMs / 86400000))
  })

  // 7-day window, deliberately wider than the backend's 3-day
  // subscriptions:notify-expiring sweep — this banner is a persistent,
  // low-friction nudge seen on every page load, so a longer lead time
  // gives more room to act before the harder email/Telegram warning and
  // the eventual hard block.
  const subscriptionExpiringSoon = computed(() =>
    Boolean(subscription.value?.is_usable) && subscriptionDaysLeft.value !== null && subscriptionDaysLeft.value <= 7
  )

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

  /**
   * Login/link only — if this Google account has no matching user yet, the
   * API responds with `requires_registration: true` instead of a session,
   * so applySession is skipped and the caller prompts for studio details
   * (then calls registerWithGoogle) instead of erroring.
   */
  async function loginWithGoogle(idToken) {
    const { data } = await googleAuthApi({ id_token: idToken })
    if (!data.data.requires_registration) {
      applySession(data.data, true)
    }
    return data
  }

  async function registerWithGoogle(payload) {
    const { data } = await googleRegisterApi(payload)
    applySession(data.data, true)
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
      connectEcho()
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
    connectEcho()
  }

  function clearSession() {
    setToken(null)
    user.value = null
    disconnectEcho()
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
    subscription,
    subscriptionDaysLeft,
    subscriptionExpiringSoon,
    hasRole,
    hasPermission,
    hasFeature,
    register,
    login,
    loginWithGoogle,
    registerWithGoogle,
    verifyTwoFactor,
    logout,
    fetchMe,
    initialize,
    clearSession,
  }
})
