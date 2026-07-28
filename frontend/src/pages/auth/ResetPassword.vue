<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { Field } from 'vee-validate'
import AppForm from '@/components/common/AppForm.vue'
import { resetPasswordSchema } from '@/utils/validators'
import { authService } from '@/services/auth.service'

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
    await authService.resetPassword({
      ...values,
      token: route.query.token,
      email: route.query.email,
    })
    router.push({ name: 'login' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to reset password. The link may have expired.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-h6 font-weight-bold mb-4">{{ t('auth.resetPassword') }}</h2>

    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="resetPasswordSchema" :initial-values="{ password: '', password_confirmation: '' }" @submit="onSubmit">
      <template #default="{ errors }">
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

        <Field v-slot="{ field }" name="password_confirmation">
          <v-text-field
            v-bind="field"
            :label="t('auth.confirmPassword')"
            :type="showPassword ? 'text' : 'password'"
            prepend-inner-icon="mdi-lock-check-outline"
            :error-messages="errors.password_confirmation"
            class="mb-4"
          />
        </Field>

        <v-btn type="submit" color="primary" block size="large" :loading="loading">
          {{ t('auth.resetPassword') }}
        </v-btn>
      </template>
    </AppForm>
  </div>
</template>
