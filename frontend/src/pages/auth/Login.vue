<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppForm from '@/components/common/AppForm.vue'
import { loginSchema } from '@/utils/validators'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { useGoogleIdentity } from '@/composables/useGoogleIdentity'

const { t, locale } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const appStore = useAppStore()
const { initialize, renderButton } = useGoogleIdentity()
let googleClient = null

const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)
const googleButtonRef = ref(null)

// Set once a login response comes back with requires_two_factor — switches
// the page into the code-entry step instead of navigating away.
const twoFactorToken = ref(null)
const rememberPending = ref(false)
const twoFactorCode = ref('')
const useRecoveryCode = ref(false)

function goToDestination() {
  appStore.notify(t('auth.loginSuccess'))
  const fallback = auth.isSuperAdmin ? { name: 'admin-analytics' } : { name: 'dashboard' }
  router.push(route.query.redirect || fallback)
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await auth.login(values)
    if (response.data.requires_two_factor) {
      twoFactorToken.value = response.data.two_factor_token
      rememberPending.value = Boolean(values.remember)
    } else {
      goToDestination()
    }
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.loginError')
  } finally {
    loading.value = false
  }
}

async function onSubmitTwoFactor() {
  loading.value = true
  errorMessage.value = ''

  try {
    await auth.verifyTwoFactor({
      two_factor_token: twoFactorToken.value,
      code: twoFactorCode.value,
      remember: rememberPending.value,
    })
    goToDestination()
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.twoFactor.verifyError')
  } finally {
    loading.value = false
  }
}

function backToLogin() {
  twoFactorToken.value = null
  twoFactorCode.value = ''
  useRecoveryCode.value = false
  errorMessage.value = ''
}

async function handleGoogleCredential(idToken) {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await auth.loginWithGoogle(idToken)
    if (response.data.requires_registration) {
      errorMessage.value = t('auth.googleAccountNotRegistered')
    } else {
      goToDestination()
    }
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.googleSignInError')
  } finally {
    loading.value = false
  }
}

// initialize() is safe to call on every mount (the underlying Google call
// only ever fires once, see useGoogleIdentity.js); renderButton() re-renders
// whenever the app's language changes, so the button's own text updates
// live without needing a page reload.
watch(
  locale,
  async () => {
    googleClient ??= await initialize(handleGoogleCredential)
    renderButton(googleButtonRef.value, googleClient, { locale: locale.value })
  },
  { immediate: true },
)
</script>

<template>
  <div>
    <div v-if="!twoFactorToken" class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.loginTitle') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.loginSubtitle') }}</p>
    </div>
    <div v-else class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.twoFactor.title') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.twoFactor.subtitle') }}</p>
    </div>

    <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-6" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <form v-if="twoFactorToken" @submit.prevent="onSubmitTwoFactor">
      <v-otp-input
        v-if="!useRecoveryCode"
        v-model="twoFactorCode"
        length="6"
        autofocus
        class="mb-2"
        :disabled="loading"
        @finish="onSubmitTwoFactor"
      />
      <v-text-field
        v-else
        v-model="twoFactorCode"
        :label="t('auth.twoFactor.recoveryCodeLabel')"
        autofocus
        class="mb-2"
      />
      <p class="text-body-2 text-medium-emphasis text-center mb-6">{{ t('auth.twoFactor.codeHint') }}</p>

      <v-btn type="submit" color="primary" block size="large" :loading="loading" :disabled="!twoFactorCode" class="auth-submit">
        {{ t('auth.twoFactor.verify') }}
      </v-btn>

      <div class="text-center mt-6 d-flex flex-column ga-2">
        <a href="#" class="text-body-2 font-weight-medium auth-link" @click.prevent="useRecoveryCode = !useRecoveryCode; twoFactorCode = ''">
          {{ useRecoveryCode ? t('auth.twoFactor.useCodeInstead') : t('auth.twoFactor.useRecoveryCodeInstead') }}
        </a>
        <a href="#" class="text-body-2 font-weight-medium auth-link" @click.prevent="backToLogin">
          {{ t('auth.twoFactor.backToLogin') }}
        </a>
      </div>
    </form>

    <template v-if="!twoFactorToken">
      <div ref="googleButtonRef" class="d-flex justify-center mb-4"></div>

      <div class="d-flex align-center ga-3 mb-6">
        <v-divider />
        <span class="text-caption text-medium-emphasis text-no-wrap">{{ t('auth.orContinueWith') }}</span>
        <v-divider />
      </div>
    </template>

    <AppForm v-if="!twoFactorToken" :schema="loginSchema" :initial-values="{ email: '', password: '', remember: false }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-text-field
          :model-value="values.email"
          :label="t('auth.email')"
          type="email"
          autocomplete="username"
          prepend-inner-icon="mdi-email-outline"
          :error-messages="errors.email"
          class="mb-4"
          @update:model-value="setFieldValue('email', $event)"
        />

        <v-text-field
          :model-value="values.password"
          :label="t('auth.password')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
          :error-messages="errors.password"
          class="mb-2"
          @update:model-value="setFieldValue('password', $event)"
          @click:append-inner="showPassword = !showPassword"
        />

        <div class="d-flex align-center justify-space-between mb-6">
          <v-checkbox
            :model-value="values.remember"
            :label="t('auth.rememberMe')"
            hide-details
            density="compact"
            @update:model-value="setFieldValue('remember', $event)"
          />

          <router-link :to="{ name: 'forgot-password' }" class="text-body-2 font-weight-medium auth-link">
            {{ t('auth.forgotPassword') }}
          </router-link>
        </div>

        <v-btn type="submit" color="primary" block size="large" :loading="loading" class="auth-submit">
          {{ t('auth.login') }}
        </v-btn>

        <div class="text-center mt-6 text-body-2 text-medium-emphasis">
          {{ t('auth.dontHaveAccount') }}
          <router-link :to="{ name: 'register' }" class="font-weight-medium auth-link">{{ t('auth.createAccount') }}</router-link>
        </div>
      </template>
    </AppForm>
  </div>
</template>

<style scoped>
.auth-link {
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
}

.auth-link:hover {
  text-decoration: underline;
}

.auth-submit {
  letter-spacing: 0.02em;
}
</style>
