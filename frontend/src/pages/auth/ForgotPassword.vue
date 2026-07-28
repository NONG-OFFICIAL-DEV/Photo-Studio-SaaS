<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
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
    errorMessage.value = error.response?.data?.message || 'Something went wrong.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-h6 font-weight-bold mb-2">{{ t('auth.resetPassword') }}</h2>
    <p class="text-body-2 text-medium-emphasis mb-4">
      Enter your email and we'll send you a link to reset your password.
    </p>

    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>
    <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-4">{{ successMessage }}</v-alert>

    <AppForm :schema="forgotPasswordSchema" :initial-values="{ email: '' }" @submit="onSubmit">
      <template #default="{ errors }">
        <Field v-slot="{ field }" name="email">
          <v-text-field
            v-bind="field"
            :label="t('auth.email')"
            type="email"
            prepend-inner-icon="mdi-email-outline"
            :error-messages="errors.email"
            class="mb-4"
          />
        </Field>

        <v-btn type="submit" color="primary" block size="large" :loading="loading">
          {{ t('auth.sendResetLink') }}
        </v-btn>

        <div class="text-center mt-4 text-body-2">
          <router-link :to="{ name: 'login' }">{{ t('common.back') }}</router-link>
        </div>
      </template>
    </AppForm>
  </div>
</template>
