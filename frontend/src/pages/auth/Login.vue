<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { Field } from 'vee-validate'
import AppForm from '@/components/common/AppForm.vue'
import { loginSchema } from '@/utils/validators'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

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
    router.push(route.query.redirect || { name: 'dashboard' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Login failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-h6 font-weight-bold mb-4">{{ t('auth.loginTitle') }}</h2>

    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <AppForm :schema="loginSchema" :initial-values="{ email: '', password: '', remember: false }" @submit="onSubmit">
      <template #default="{ errors }">
        <Field v-slot="{ field }" name="email">
          <v-text-field
            v-bind="field"
            :label="t('auth.email')"
            type="email"
            prepend-inner-icon="mdi-email-outline"
            :error-messages="errors.email"
            class="mb-2"
          />
        </Field>

        <Field v-slot="{ field }" name="password">
          <v-text-field
            v-bind="field"
            :label="t('auth.password')"
            :type="showPassword ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-outline"
            :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
            :error-messages="errors.password"
            class="mb-2"
            @click:append-inner="showPassword = !showPassword"
          />
        </Field>

        <div class="d-flex align-center justify-space-between mb-4">
          <Field v-slot="{ field, value }" name="remember" type="checkbox">
            <v-checkbox
              v-bind="field"
              :model-value="value"
              :label="t('auth.rememberMe')"
              hide-details
              density="compact"
            />
          </Field>

          <router-link :to="{ name: 'forgot-password' }" class="text-body-2">
            {{ t('auth.forgotPassword') }}
          </router-link>
        </div>

        <v-btn type="submit" color="primary" block size="large" :loading="loading">
          {{ t('auth.login') }}
        </v-btn>

        <div class="text-center mt-4 text-body-2">
          {{ t('auth.dontHaveAccount') }}
          <router-link :to="{ name: 'register' }">{{ t('auth.createAccount') }}</router-link>
        </div>
      </template>
    </AppForm>
  </div>
</template>
