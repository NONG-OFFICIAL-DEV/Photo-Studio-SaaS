<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import { getAdminPlatformSettingsApi, updateAdminPlatformSettingsApi, uploadAdminKhqrImageApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(true)
const saving = ref(false)
const uploading = ref(false)
const khqrInput = ref(null)
const settings = ref({
  khqr_image_url: null,
  bank_name: '',
  bank_account_name: '',
  bank_account_number: '',
  payment_instructions: '',
})

async function load() {
  loading.value = true
  try {
    const { data } = await getAdminPlatformSettingsApi()
    settings.value = data.data
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  try {
    const { data } = await updateAdminPlatformSettingsApi({
      bank_name: settings.value.bank_name,
      bank_account_name: settings.value.bank_account_name,
      bank_account_number: settings.value.bank_account_number,
      payment_instructions: settings.value.payment_instructions,
    })
    settings.value = data.data
    appStore.notify(t('admin.paymentSettings.messages.saved'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.paymentSettings.messages.saveError'), 'error')
  } finally {
    saving.value = false
  }
}

function triggerUpload() {
  khqrInput.value?.click()
}

async function onKhqrSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return

  uploading.value = true
  try {
    const { data } = await uploadAdminKhqrImageApi(file)
    settings.value = data.data
    appStore.notify(t('admin.paymentSettings.messages.khqrUploaded'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.paymentSettings.messages.khqrUploadError'), 'error')
  } finally {
    uploading.value = false
    event.target.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.paymentSettings.title')" :subtitle="t('admin.paymentSettings.subtitle')" />

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-card v-else variant="flat" border rounded="lg" class="pa-4">
      <v-row>
        <v-col cols="12" md="4" class="text-center">
          <v-avatar size="180" rounded="lg" class="mb-3 border">
            <v-img v-if="settings.khqr_image_url" :src="settings.khqr_image_url" cover />
            <v-icon v-else icon="mdi-qrcode" size="72" />
          </v-avatar>
          <div>
            <input ref="khqrInput" type="file" accept="image/*" class="d-none" @change="onKhqrSelected" />
            <v-btn variant="outlined" prepend-icon="mdi-qrcode" :loading="uploading" @click="triggerUpload">
              {{ t('admin.paymentSettings.uploadKhqr') }}
            </v-btn>
            <p class="text-caption text-medium-emphasis mt-2">{{ t('admin.paymentSettings.khqrHint') }}</p>
          </div>
        </v-col>

        <v-col cols="12" md="8">
          <v-text-field
            v-model="settings.bank_name"
            :label="t('admin.paymentSettings.bankName')"
            class="mb-2"
          />
          <v-text-field
            v-model="settings.bank_account_name"
            :label="t('admin.paymentSettings.bankAccountName')"
            class="mb-2"
          />
          <v-text-field
            v-model="settings.bank_account_number"
            :label="t('admin.paymentSettings.bankAccountNumber')"
            class="mb-2"
          />
          <v-textarea
            v-model="settings.payment_instructions"
            :label="t('admin.paymentSettings.paymentInstructions')"
            rows="3"
            class="mb-2"
          />
          <v-btn color="primary" variant="flat" :loading="saving" @click="handleSave">
            {{ t('common.save') }}
          </v-btn>
        </v-col>
      </v-row>
    </v-card>
  </div>
</template>
