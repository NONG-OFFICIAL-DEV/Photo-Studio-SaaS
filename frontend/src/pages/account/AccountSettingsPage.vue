<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppForm from '@/components/common/AppForm.vue'
import { updateEmailSchema, updatePasswordSchema } from '@/utils/validators'
import {
  updateEmailApi,
  updatePasswordApi,
  setupTwoFactorApi,
  confirmTwoFactorApi,
  disableTwoFactorApi,
} from '@/apis/auth.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const emailFormId = useId()
const passwordFormId = useId()

const savingEmail = ref(false)
const emailError = ref('')
const savingPassword = ref(false)
const passwordError = ref('')

const showEmailFormPassword = ref(false)
const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)
const showDisablePassword = ref(false)

/*
 * A 422 here is always a Laravel FormRequest failure: the generic top-level
 * `message` is just "The given data was invalid." (see bootstrap/app.php's
 * ValidationException handler) — the actual reason (e.g. "The current
 * password is incorrect.") lives under meta.errors.<field>. Prefer that
 * specific message when present, since translateApiMessage alone would
 * otherwise show the unhelpful generic one.
 */
function firstFieldError(error, fields) {
  const errors = error?.response?.data?.meta?.errors
  for (const field of fields) {
    if (errors?.[field]?.[0]) return errors[field][0]
  }
  return null
}

async function handleUpdateEmail(values, { resetForm }) {
  savingEmail.value = true
  emailError.value = ''
  try {
    await updateEmailApi(values)
    await auth.fetchMe()
    appStore.notify(t('account.messages.emailUpdated'))
    resetForm({ values: { current_password: '', email: values.email } })
  } catch (error) {
    emailError.value = firstFieldError(error, ['current_password', 'email'])
      ?? translateApiMessage(error, 'account.messages.emailUpdateError')
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
    passwordError.value = firstFieldError(error, ['current_password', 'password'])
      ?? translateApiMessage(error, 'account.messages.passwordUpdateError')
  } finally {
    savingPassword.value = false
  }
}

// 'idle' (enabled or not, nothing in progress) -> 'enrolling' (QR shown,
// waiting for a confirm code) -> 'recovery-codes' (shown once, then back
// to idle once acknowledged).
const twoFactorStep = ref('idle')
const twoFactorSetup = ref(null)
const twoFactorConfirmCode = ref('')
const twoFactorRecoveryCodes = ref([])
const startingTwoFactor = ref(false)
const confirmingTwoFactor = ref(false)
const twoFactorError = ref('')

const disablePassword = ref('')
const disablingTwoFactor = ref(false)
const disableError = ref('')

async function startTwoFactorEnrollment() {
  startingTwoFactor.value = true
  twoFactorError.value = ''
  try {
    const { data } = await setupTwoFactorApi()
    twoFactorSetup.value = data.data
    twoFactorStep.value = 'enrolling'
  } catch (error) {
    twoFactorError.value = translateApiMessage(error, 'account.twoFactor.messages.setupError')
  } finally {
    startingTwoFactor.value = false
  }
}

async function confirmTwoFactorEnrollment() {
  confirmingTwoFactor.value = true
  twoFactorError.value = ''
  try {
    const { data } = await confirmTwoFactorApi({ code: twoFactorConfirmCode.value })
    twoFactorRecoveryCodes.value = data.data.recovery_codes
    twoFactorStep.value = 'recovery-codes'
    await auth.fetchMe()
  } catch (error) {
    twoFactorError.value = firstFieldError(error, ['code'])
      ?? translateApiMessage(error, 'account.twoFactor.messages.confirmError')
  } finally {
    confirmingTwoFactor.value = false
  }
}

function cancelTwoFactorEnrollment() {
  twoFactorStep.value = 'idle'
  twoFactorSetup.value = null
  twoFactorConfirmCode.value = ''
  twoFactorError.value = ''
}

function acknowledgeRecoveryCodes() {
  twoFactorStep.value = 'idle'
  twoFactorSetup.value = null
  twoFactorConfirmCode.value = ''
  twoFactorRecoveryCodes.value = []
}

async function handleDisableTwoFactor() {
  disablingTwoFactor.value = true
  disableError.value = ''
  try {
    await disableTwoFactorApi({ current_password: disablePassword.value })
    disablePassword.value = ''
    appStore.notify(t('account.twoFactor.messages.disabled'))
    await auth.fetchMe()
  } catch (error) {
    disableError.value = firstFieldError(error, ['current_password'])
      ?? translateApiMessage(error, 'account.twoFactor.messages.disableError')
  } finally {
    disablingTwoFactor.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('account.dialogTitle')" />

    <v-card variant="outlined" class="mb-4">
      <v-card-text>
        <h3 class="text-subtitle-1 font-weight-bold mb-2">{{ t('account.changeEmail') }}</h3>
        <v-alert v-if="emailError" type="error" variant="tonal" class="mb-3">{{ emailError }}</v-alert>

        <AppForm
          :id="emailFormId"
          :schema="updateEmailSchema"
          :initial-values="{ current_password: '', email: auth.user?.email ?? '' }"
          @submit="handleUpdateEmail"
        >
          <template #default="{ errors, values, setFieldValue }">
            <v-row>
              <v-col cols="12" sm="6">
                <v-text-field
                  :model-value="values.email"
                  :label="t('fields.email')"
                  type="email"
                  :error-messages="errors.email"
                  @update:model-value="setFieldValue('email', $event)"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  :model-value="values.current_password"
                  :label="t('account.currentPassword')"
                  :type="showEmailFormPassword ? 'text' : 'password'"
                  :append-inner-icon="showEmailFormPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :error-messages="errors.current_password"
                  @update:model-value="setFieldValue('current_password', $event)"
                  @click:append-inner="showEmailFormPassword = !showEmailFormPassword"
                />
              </v-col>
            </v-row>
          </template>
        </AppForm>

        <div class="d-flex justify-end">
          <v-btn type="submit" :form="emailFormId" color="primary" variant="flat" :loading="savingEmail">
            {{ t('account.updateEmail') }}
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="mb-4">
      <v-card-text>
        <h3 class="text-subtitle-1 font-weight-bold mb-2">{{ t('account.changePassword') }}</h3>
        <v-alert v-if="passwordError" type="error" variant="tonal" class="mb-3">{{ passwordError }}</v-alert>

        <AppForm
          :id="passwordFormId"
          :schema="updatePasswordSchema"
          :initial-values="{ current_password: '', password: '', password_confirmation: '' }"
          @submit="handleUpdatePassword"
        >
          <template #default="{ errors, values, setFieldValue }">
            <v-row>
              <v-col cols="12" sm="4">
                <v-text-field
                  :model-value="values.current_password"
                  :label="t('account.currentPassword')"
                  :type="showCurrentPassword ? 'text' : 'password'"
                  :append-inner-icon="showCurrentPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :error-messages="errors.current_password"
                  @update:model-value="setFieldValue('current_password', $event)"
                  @click:append-inner="showCurrentPassword = !showCurrentPassword"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  :model-value="values.password"
                  :label="t('account.newPassword')"
                  :type="showNewPassword ? 'text' : 'password'"
                  :append-inner-icon="showNewPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :error-messages="errors.password"
                  @update:model-value="setFieldValue('password', $event)"
                  @click:append-inner="showNewPassword = !showNewPassword"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  :model-value="values.password_confirmation"
                  :label="t('account.confirmNewPassword')"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  :append-inner-icon="showConfirmPassword ? 'mdi-eye-off' : 'mdi-eye'"
                  :error-messages="errors.password_confirmation"
                  @update:model-value="setFieldValue('password_confirmation', $event)"
                  @click:append-inner="showConfirmPassword = !showConfirmPassword"
                />
              </v-col>
            </v-row>
          </template>
        </AppForm>

        <div class="d-flex justify-end">
          <v-btn type="submit" :form="passwordFormId" color="primary" variant="flat" :loading="savingPassword">
            {{ t('account.updatePassword') }}
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="auth.isSuperAdmin" variant="outlined">
      <v-card-text>
        <h3 class="text-subtitle-1 font-weight-bold mb-2">{{ t('account.twoFactor.title') }}</h3>
        <v-alert v-if="twoFactorError" type="error" variant="tonal" class="mb-3">{{ twoFactorError }}</v-alert>

        <template v-if="twoFactorStep === 'idle'">
          <p class="text-body-2 text-medium-emphasis mb-3">{{ t('account.twoFactor.description') }}</p>

          <div v-if="auth.user?.two_factor_enabled">
            <v-alert type="success" variant="tonal" density="compact" class="mb-3">
              {{ t('account.twoFactor.enabledNotice') }}
            </v-alert>
            <v-alert v-if="disableError" type="error" variant="tonal" class="mb-3">{{ disableError }}</v-alert>
            <v-row align="center">
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="disablePassword"
                  :label="t('account.currentPassword')"
                  :type="showDisablePassword ? 'text' : 'password'"
                  :append-inner-icon="showDisablePassword ? 'mdi-eye-off' : 'mdi-eye'"
                  hide-details
                  @click:append-inner="showDisablePassword = !showDisablePassword"
                />
              </v-col>
              <v-col cols="12" sm="6" class="d-flex justify-sm-end">
                <v-btn
                  color="error"
                  variant="flat"
                  :loading="disablingTwoFactor"
                  :disabled="!disablePassword"
                  @click="handleDisableTwoFactor"
                >
                  {{ t('account.twoFactor.disable') }}
                </v-btn>
              </v-col>
            </v-row>
          </div>
          <div v-else>
            <v-btn color="primary" variant="flat" :loading="startingTwoFactor" @click="startTwoFactorEnrollment">
              {{ t('account.twoFactor.enable') }}
            </v-btn>
          </div>
        </template>

        <template v-else-if="twoFactorStep === 'enrolling'">
          <p class="text-body-2 text-medium-emphasis mb-3">{{ t('account.twoFactor.scanHint') }}</p>

          <div class="d-flex justify-center mb-3 two-factor-qr" v-html="twoFactorSetup?.qr_code_svg" />

          <p class="text-caption text-medium-emphasis text-center mb-4">
            {{ t('account.twoFactor.manualEntry') }}: <code>{{ twoFactorSetup?.secret }}</code>
          </p>

          <div class="d-flex justify-center mb-4">
            <v-otp-input
              v-model="twoFactorConfirmCode"
              length="6"
              :disabled="confirmingTwoFactor"
              style="max-width: 320px"
              @finish="confirmTwoFactorEnrollment"
            />
          </div>

          <div class="d-flex justify-end ga-2">
            <v-btn variant="text" :disabled="confirmingTwoFactor" @click="cancelTwoFactorEnrollment">
              {{ t('common.cancel') }}
            </v-btn>
            <v-btn
              color="primary"
              variant="flat"
              :loading="confirmingTwoFactor"
              :disabled="!twoFactorConfirmCode"
              @click="confirmTwoFactorEnrollment"
            >
              {{ t('account.twoFactor.confirm') }}
            </v-btn>
          </div>
        </template>

        <template v-else-if="twoFactorStep === 'recovery-codes'">
          <v-alert type="warning" variant="tonal" class="mb-3">
            {{ t('account.twoFactor.recoveryCodesHint') }}
          </v-alert>

          <div class="recovery-codes mb-4">
            <code v-for="recoveryCode in twoFactorRecoveryCodes" :key="recoveryCode">{{ recoveryCode }}</code>
          </div>

          <div class="d-flex justify-end">
            <v-btn color="primary" variant="flat" @click="acknowledgeRecoveryCodes">
              {{ t('account.twoFactor.savedRecoveryCodes') }}
            </v-btn>
          </div>
        </template>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.two-factor-qr :deep(svg) {
  width: 180px;
  height: 180px;
}

.recovery-codes {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  text-align: center;
  max-width: 320px;
  margin-inline: auto;
}

.recovery-codes code {
  padding: 4px 8px;
}
</style>
