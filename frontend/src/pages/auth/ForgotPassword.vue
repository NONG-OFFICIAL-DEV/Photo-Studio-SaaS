<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppForm from '@/components/common/AppForm.vue'
import { forgotPasswordSchema } from '@/utils/validators'
import { forgotPasswordApi } from '@/apis/auth.api'

const { t } = useI18n()

const loading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const { data } = await forgotPasswordApi(values)
    successMessage.value = data.message
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('auth.forgotPasswordError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <div class="mb-8">
      <h1 class="text-h4 font-weight-bold mb-2">{{ t('auth.resetPassword') }}</h1>
      <p class="text-body-2 text-medium-emphasis">{{ t('auth.forgotPasswordSubtitle') }}</p>
    </div>

    <v-alert v-if="errorMessage" type="error" variant="tonal" rounded="lg" class="mb-6">{{ errorMessage }}</v-alert>
    <v-alert v-if="successMessage" type="success" variant="tonal" rounded="lg" class="mb-6">{{ successMessage }}</v-alert>

    <AppForm :schema="forgotPasswordSchema" :initial-values="{ email: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-text-field
          :model-value="values.email"
          :label="t('auth.email')"
          type="email"
          autocomplete="username"
          prepend-inner-icon="mdi-email-outline"
          :error-messages="errors.email"
          class="mb-6"
          @update:model-value="setFieldValue('email', $event)"
        />

        <v-btn type="submit" color="primary" block size="large" :loading="loading" class="auth-submit">
          {{ t('auth.sendResetLink') }}
        </v-btn>

        <div class="text-center mt-6 text-body-2">
          <router-link :to="{ name: 'login' }" class="font-weight-medium auth-link">{{ t('common.back') }}</router-link>
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
