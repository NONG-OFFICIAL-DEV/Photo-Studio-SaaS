<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppForm from '@/components/common/AppForm.vue'
import { registerSchema } from '@/utils/validators'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { useGoogleIdentity } from '@/composables/useGoogleIdentity'

const { t, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const appStore = useAppStore()
const { initialize, renderButton } = useGoogleIdentity()
let googleClient = null

const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)
const pendingGoogleIdToken = ref(null)

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await auth.register(values)
    appStore.notify(t('auth.registerSuccess'))
    router.push({ name: 'dashboard' })
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.registerError')
  } finally {
    loading.value = false
  }
}

/**
 * Google can't supply a studio name, so the Google button lives inside the
 * same form as the "Studio Name" field — clicking it stores the credential
 * and, if a studio name is already typed, submits immediately; otherwise it
 * waits (the credential is submitted the moment the name field gets one).
 */
async function submitGoogleRegistration(idToken, studioName, phone) {
  loading.value = true
  errorMessage.value = ''

  try {
    await auth.registerWithGoogle({
      id_token: idToken,
      studio_name: studioName,
      phone: phone || null,
    })
    appStore.notify(t('auth.registerSuccess'))
    router.push({ name: 'dashboard' })
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.googleSignInError')
    pendingGoogleIdToken.value = null
  } finally {
    loading.value = false
  }
}

// `values` is vee-validate's reactive object, mutated in place across
// renders — reading values.studio_name/phone inside this closure at click
// time (not at render time it was created) always sees the latest typed
// value. initialize() is safe to call every time this fires (the underlying
// Google call only ever fires once, see useGoogleIdentity.js); renderButton() is
// re-called (not just once ever) whenever the app's language changes, so
// the button's own text updates live — but NOT on every re-render (e.g.
// typing in other fields also re-renders this component via reactive
// `t()` calls), or the button would flicker/re-mount constantly.
let lastRenderedLocale = null
async function onGoogleButtonMount(el, values) {
  if (!el || lastRenderedLocale === locale.value) return
  lastRenderedLocale = locale.value

  googleClient ??= await initialize((idToken) => {
    if (values.studio_name) {
      submitGoogleRegistration(idToken, values.studio_name, values.phone)
    } else {
      // Held until the studio name field is filled in (see
      // handleStudioNameInput below) — Google can't supply one.
      pendingGoogleIdToken.value = idToken
    }
  })

  renderButton(el, googleClient, { locale: locale.value })
}

function handleStudioNameInput(value, setFieldValue, values) {
  setFieldValue('studio_name', value)

  if (pendingGoogleIdToken.value) {
    const idToken = pendingGoogleIdToken.value
    pendingGoogleIdToken.value = null
    submitGoogleRegistration(idToken, value, values.phone)
  }
}
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.registerTitle') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.registerSubtitle') }}</p>
    </div>

    <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-6" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <AppForm
      :schema="registerSchema"
      :initial-values="{ studio_name: '', owner_name: '', email: '', phone: '', password: '', password_confirmation: '' }"
      @submit="onSubmit"
    >
      <template #default="{ errors, values, setFieldValue }">
        <div class="auth-row-split mb-4">
          <v-text-field
            :model-value="values.studio_name"
            :label="t('auth.studioName')"
            prepend-inner-icon="mdi-domain"
            :error-messages="errors.studio_name"
            hide-details="auto"
            @update:model-value="handleStudioNameInput($event, setFieldValue, values)"
          />

          <v-text-field :model-value="values.owner_name" :label="t('auth.ownerName')" prepend-inner-icon="mdi-account-outline" :error-messages="errors.owner_name" hide-details="auto" @update:model-value="setFieldValue('owner_name', $event)" />
        </div>

        <div :ref="(el) => onGoogleButtonMount(el, values)" class="d-flex justify-center mb-4"></div>

        <div class="d-flex align-center ga-3 mb-6">
          <v-divider />
          <span class="text-caption text-medium-emphasis text-no-wrap">{{ t('auth.orFillInDetails') }}</span>
          <v-divider />
        </div>

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

        <v-text-field :model-value="values.phone" :label="t('auth.phone')" prepend-inner-icon="mdi-phone-outline" :error-messages="errors.phone" class="mb-4" @update:model-value="setFieldValue('phone', $event)" />

        <div class="auth-row-split mb-4">
          <v-text-field
            :model-value="values.password"
            :label="t('auth.password')"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            prepend-inner-icon="mdi-lock-outline"
            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
            :error-messages="errors.password"
            hide-details="auto"
            @update:model-value="setFieldValue('password', $event)"
            @click:append-inner="showPassword = !showPassword"
          />
        </div>

        <v-text-field
          :model-value="values.password_confirmation"
          :label="t('auth.confirmPassword')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          prepend-inner-icon="mdi-lock-check-outline"
          :error-messages="errors.password_confirmation"
          hide-details="auto"
          @update:model-value="setFieldValue('password_confirmation', $event)"
        />

        <v-btn type="submit" color="primary" block size="large" :loading="loading" class="auth-submit mt-2">
          {{ t('auth.createAccount') }}
        </v-btn>

        <div class="text-center mt-6 text-body-2 text-medium-emphasis">
          {{ t('auth.alreadyHaveAccount') }}
          <router-link :to="{ name: 'login' }" class="font-weight-medium auth-link">{{ t('auth.login') }}</router-link>
        </div>
      </template>
    </AppForm>
  </div>
</template>

<style scoped>
.auth-row-split {
  display: flex;
  gap: 12px;
}

.auth-row-split > * {
  flex: 1 1 0;
  min-width: 0;
}

@media (max-width: 600px) {
  .auth-row-split {
    flex-direction: column;
    gap: 16px;
  }
}

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
