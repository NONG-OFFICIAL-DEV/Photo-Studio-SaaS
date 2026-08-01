<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resendVerificationApi } from '@/apis/auth.api'
import { translateApiMessage } from '@/utils/apiMessages'
import { useAppStore } from '@/stores/app'

/*
 * Drop-in replacement for the plain `<v-alert>{{ errorMessage }}</v-alert>`
 * pattern repeated across every form dialog — takes the raw caught error
 * (not a pre-translated string) so it can also react to the error's code,
 * not just its message. Right now that's used for exactly one case:
 * EMAIL_NOT_VERIFIED gets a "Resend verification email" button right in
 * the alert, since otherwise a user blocked from e.g. inviting a teammate
 * has no way to act on that message without leaving the dialog to find
 * the resend button buried on the Dashboard.
 */
const props = defineProps({
  error: { type: [Object, Error, null], default: null },
  fallbackKey: { type: String, default: null },
})

const { t } = useI18n()
const appStore = useAppStore()
const resendLoading = ref(false)

const code = computed(() => props.error?.response?.data?.code ?? null)
const message = computed(() => (props.error ? translateApiMessage(props.error, props.fallbackKey) : ''))

async function resend() {
  resendLoading.value = true
  try {
    const { data } = await resendVerificationApi()
    appStore.notify(t(`apiErrors.${data.code}`))
  } finally {
    resendLoading.value = false
  }
}
</script>

<template>
  <v-alert v-if="message" type="error" variant="tonal" class="mb-4">
    <div class="d-flex align-center justify-space-between flex-wrap ga-2">
      <span>{{ message }}</span>
      <v-btn v-if="code === 'EMAIL_NOT_VERIFIED'" size="small" variant="tonal" :loading="resendLoading" @click="resend">
        {{ t('auth.resendVerification') }}
      </v-btn>
    </div>
  </v-alert>
</template>
