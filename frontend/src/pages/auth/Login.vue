<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppForm from '@/components/common/AppForm.vue'
import { loginSchema } from '@/utils/validators'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await auth.login(values)
    appStore.notify(t('auth.loginSuccess'))
    const fallback = auth.isSuperAdmin ? { name: 'admin-analytics' } : { name: 'dashboard' }
    router.push(route.query.redirect || fallback)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'auth.loginError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.loginTitle') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.loginSubtitle') }}</p>
    </div>

    <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-6" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <AppForm :schema="loginSchema" :initial-values="{ email: '', password: '', remember: false }" @submit="onSubmit">
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
