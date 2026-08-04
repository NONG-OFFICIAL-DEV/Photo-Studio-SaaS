<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { updateEmailSchema, updatePasswordSchema } from '@/utils/validators'
import { updateEmailApi, updatePasswordApi } from '@/apis/auth.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const emailFormId = useId()
const passwordFormId = useId()

const savingEmail = ref(false)
const emailError = ref('')
const savingPassword = ref(false)
const passwordError = ref('')

async function handleUpdateEmail(values, { resetForm }) {
  savingEmail.value = true
  emailError.value = ''
  try {
    await updateEmailApi(values)
    await auth.fetchMe()
    appStore.notify(t('account.messages.emailUpdated'))
    resetForm({ values: { current_password: '', email: values.email } })
  } catch (error) {
    emailError.value = translateApiMessage(error, 'account.messages.emailUpdateError')
  } finally {
    savingEmail.value = false
  }
}

async function handleUpdatePassword(values, { resetForm }) {
  savingPassword.value = true
  passwordError.value = ''
  try {
    await updatePasswordApi(values)
    appStore.notify(t('account.messages.passwordUpdated'))
    resetForm({ values: { current_password: '', password: '', password_confirmation: '' } })
  } catch (error) {
    passwordError.value = translateApiMessage(error, 'account.messages.passwordUpdateError')
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('account.dialogTitle')"
    max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <h3 class="text-subtitle-1 font-weight-bold mb-2">{{ t('account.changeEmail') }}</h3>
    <v-alert v-if="emailError" type="error" variant="tonal" class="mb-3">{{ emailError }}</v-alert>

    <AppForm
      :id="emailFormId"
      :schema="updateEmailSchema"
      :initial-values="{ current_password: '', email: auth.user?.email ?? '' }"
      @submit="handleUpdateEmail"
    >
      <template #default="{ errors, values, setFieldValue }">
        <v-text-field
          :model-value="values.email"
          :label="t('fields.email')"
          type="email"
          :error-messages="errors.email"
          class="mb-2"
          @update:model-value="setFieldValue('email', $event)"
        />
        <v-text-field
          :model-value="values.current_password"
          :label="t('account.currentPassword')"
          type="password"
          :error-messages="errors.current_password"
          @update:model-value="setFieldValue('current_password', $event)"
        />
      </template>
    </AppForm>

    <div class="d-flex justify-end mb-4">
      <v-btn type="submit" :form="emailFormId" color="primary" variant="flat" :loading="savingEmail">
        {{ t('account.updateEmail') }}
      </v-btn>
    </div>

    <v-divider class="mb-4" />

    <h3 class="text-subtitle-1 font-weight-bold mb-2">{{ t('account.changePassword') }}</h3>
    <v-alert v-if="passwordError" type="error" variant="tonal" class="mb-3">{{ passwordError }}</v-alert>

    <AppForm
      :id="passwordFormId"
      :schema="updatePasswordSchema"
      :initial-values="{ current_password: '', password: '', password_confirmation: '' }"
      @submit="handleUpdatePassword"
    >
      <template #default="{ errors, values, setFieldValue }">
        <v-text-field
          :model-value="values.current_password"
          :label="t('account.currentPassword')"
          type="password"
          :error-messages="errors.current_password"
          class="mb-2"
          @update:model-value="setFieldValue('current_password', $event)"
        />
        <v-text-field
          :model-value="values.password"
          :label="t('account.newPassword')"
          type="password"
          :error-messages="errors.password"
          class="mb-2"
          @update:model-value="setFieldValue('password', $event)"
        />
        <v-text-field
          :model-value="values.password_confirmation"
          :label="t('account.confirmNewPassword')"
          type="password"
          :error-messages="errors.password_confirmation"
          @update:model-value="setFieldValue('password_confirmation', $event)"
        />
      </template>
    </AppForm>

    <div class="d-flex justify-end">
      <v-btn type="submit" :form="passwordFormId" color="primary" variant="flat" :loading="savingPassword">
        {{ t('account.updatePassword') }}
      </v-btn>
    </div>

    <template #actions>
      <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.close') }}</v-btn>
    </template>
  </AppDialog>
</template>
