<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
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
    appStore.notify(t('auth.registerSuccess'))
    router.push({ name: 'dashboard' })
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('auth.registerError')
  } finally {
    loading.value = false
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
          <v-text-field :model-value="values.studio_name" :label="t('auth.studioName')" prepend-inner-icon="mdi-domain" :error-messages="errors.studio_name" hide-details="auto" @update:model-value="setFieldValue('studio_name', $event)" />

          <v-text-field :model-value="values.owner_name" :label="t('auth.ownerName')" prepend-inner-icon="mdi-account-outline" :error-messages="errors.owner_name" hide-details="auto" @update:model-value="setFieldValue('owner_name', $event)" />
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
        </div>

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
