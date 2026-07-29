<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppForm from '@/components/common/AppForm.vue'
import { resetPasswordSchema } from '@/utils/validators'
import { resetPasswordApi } from '@/apis/auth.api'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await resetPasswordApi({
      ...values,
      token: route.query.token,
      email: route.query.email,
    })
    router.push({ name: 'login' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('auth.resetPasswordError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.resetPassword') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.resetPasswordSubtitle') }}</p>
    </div>

    <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-6">{{ errorMessage }}</v-alert>

    <AppForm :schema="resetPasswordSchema" :initial-values="{ password: '', password_confirmation: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-text-field
          :model-value="values.password"
          :label="t('auth.password')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          prepend-inner-icon="mdi-lock-outline"
          :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
          :error-messages="errors.password"
          class="mb-4"
          @update:model-value="setFieldValue('password', $event)"
          @click:append-inner="showPassword = !showPassword"
        />

        <v-text-field
          :model-value="values.password_confirmation"
          :label="t('auth.confirmPassword')"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          prepend-inner-icon="mdi-lock-check-outline"
          :error-messages="errors.password_confirmation"
          class="mb-6"
          @update:model-value="setFieldValue('password_confirmation', $event)"
        />

        <v-btn type="submit" color="primary" block size="large" :loading="loading" class="auth-submit">
          {{ t('auth.resetPassword') }}
        </v-btn>
      </template>
    </AppForm>
  </div>
</template>

<style scoped>
.auth-submit {
  letter-spacing: 0.02em;
}
</style>
