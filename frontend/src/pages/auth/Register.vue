<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Field } from 'vee-validate'
import AppForm from '@/components/common/AppForm.vue'
import { registerSchema } from '@/utils/validators'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const showPassword = ref(false)

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await auth.register(values)
    appStore.notify('Studio registered successfully. Please check your email to verify your account.')
    router.push({ name: 'dashboard' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <h2 class="text-h6 font-weight-bold mb-4">{{ t('auth.registerTitle') }}</h2>

    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <AppForm
      :schema="registerSchema"
      :initial-values="{ studio_name: '', owner_name: '', email: '', phone: '', password: '', password_confirmation: '' }"
      @submit="onSubmit"
    >
      <template #default="{ errors }">
        <Field v-slot="{ field }" name="studio_name">
          <v-text-field v-bind="field" :label="t('auth.studioName')" prepend-inner-icon="mdi-domain" :error-messages="errors.studio_name" class="mb-2" />
        </Field>

        <Field v-slot="{ field }" name="owner_name">
          <v-text-field v-bind="field" :label="t('auth.ownerName')" prepend-inner-icon="mdi-account-outline" :error-messages="errors.owner_name" class="mb-2" />
        </Field>

        <Field v-slot="{ field }" name="email">
          <v-text-field v-bind="field" :label="t('auth.email')" type="email" prepend-inner-icon="mdi-email-outline" :error-messages="errors.email" class="mb-2" />
        </Field>

        <Field v-slot="{ field }" name="phone">
          <v-text-field v-bind="field" :label="t('auth.phone')" prepend-inner-icon="mdi-phone-outline" :error-messages="errors.phone" class="mb-2" />
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
          {{ t('auth.createAccount') }}
        </v-btn>

        <div class="text-center mt-4 text-body-2">
          {{ t('auth.alreadyHaveAccount') }}
          <router-link :to="{ name: 'login' }">{{ t('auth.login') }}</router-link>
        </div>
      </template>
    </AppForm>
  </div>
</template>
