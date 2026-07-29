<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppForm from '@/components/common/AppForm.vue'
import { settingsSchema } from '@/utils/validators'
import {
  getSettingsApi,
  updateSettingsApi,
  uploadSettingsLogoApi,
  exportSettingsDataApi,
} from '@/apis/settings.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const tab = ref('company')
const loading = ref(true)
const saving = ref(false)
const exporting = ref(false)
const logoUploading = ref(false)
const errorMessage = ref('')
const tenant = ref(null)
const logoInput = ref(null)

const initialValues = computed(() => ({
  name: tenant.value?.name ?? '',
  email: tenant.value?.email ?? '',
  phone: tenant.value?.phone ?? '',
  address: tenant.value?.address ?? '',
  currency: tenant.value?.currency ?? 'USD',
  timezone: tenant.value?.timezone ?? 'UTC',
  invoice_prefix: tenant.value?.settings?.invoice_prefix ?? 'INV-',
  default_tax_rate: tenant.value?.settings?.default_tax_rate ?? 0,
  default_due_days: tenant.value?.settings?.default_due_days ?? 14,
  invoice_footer: tenant.value?.settings?.invoice_footer ?? '',
  primary_color: tenant.value?.settings?.primary_color ?? '',
  secondary_color: tenant.value?.settings?.secondary_color ?? '',
}))

async function load() {
  loading.value = true
  try {
    const { data } = await getSettingsApi()
    tenant.value = data.data
  } finally {
    loading.value = false
  }
}

load()

async function onSubmit(values) {
  saving.value = true
  errorMessage.value = ''

  try {
    const { data } = await updateSettingsApi(values)
    tenant.value = data.data
    await auth.fetchMe()
    appStore.notify(t('settingsPage.messages.savedSuccess'))
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('settingsPage.messages.saveError')
  } finally {
    saving.value = false
  }
}

function triggerLogoUpload() {
  logoInput.value?.click()
}

async function onLogoSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return

  logoUploading.value = true
  try {
    const { data } = await uploadSettingsLogoApi(file)
    tenant.value = data.data
    await auth.fetchMe()
    appStore.notify(t('settingsPage.messages.logoUpdated'))
  } finally {
    logoUploading.value = false
    event.target.value = ''
  }
}

async function exportData() {
  exporting.value = true
  try {
    const { data } = await exportSettingsDataApi()
    const url = window.URL.createObjectURL(new Blob([data]))
    const link = document.createElement('a')
    link.href = url
    link.download = `data-export-${new Date().toISOString().slice(0, 10)}.zip`
    link.click()
    window.URL.revokeObjectURL(url)
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('settingsPage.title')" :subtitle="t('settingsPage.subtitle')" />

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="company">{{ t('settingsPage.tabs.company') }}</v-tab>
      <v-tab value="invoice">{{ t('settingsPage.tabs.invoice') }}</v-tab>
      <v-tab value="theme">{{ t('settingsPage.tabs.theme') }}</v-tab>
      <v-tab value="data">{{ t('settingsPage.tabs.data') }}</v-tab>
    </v-tabs>

    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else>
      <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

      <AppForm :schema="settingsSchema" :initial-values="initialValues" @submit="onSubmit">
        <template #default="{ errors, values, setFieldValue }">
          <v-window v-model="tab">
            <v-window-item value="company">
              <v-card variant="outlined">
                <v-card-text>
                  <div class="d-flex align-center ga-4 mb-4">
                    <v-avatar size="72" rounded="lg" color="surface-variant">
                      <v-img v-if="tenant?.logo_url" :src="tenant.logo_url" cover />
                      <v-icon v-else icon="mdi-domain" size="32" />
                    </v-avatar>
                    <div>
                      <input ref="logoInput" type="file" accept="image/*" class="d-none" @change="onLogoSelected" />
                      <v-btn
                        variant="outlined"
                        size="small"
                        prepend-icon="mdi-image-outline"
                        :loading="logoUploading"
                        @click="triggerLogoUpload"
                      >
                        {{ t('settingsPage.actions.uploadLogo') }}
                      </v-btn>
                    </div>
                  </div>

                  <v-row>
                    <v-col cols="12" sm="6">
                      <Field v-slot="{ field }" name="name">
                        <v-text-field v-bind="field" :label="`${t('fields.name')} *`" :error-messages="errors.name" />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <Field v-slot="{ field }" name="email">
                        <v-text-field v-bind="field" :label="`${t('fields.email')} *`" type="email" :error-messages="errors.email" />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <Field v-slot="{ field }" name="phone">
                        <v-text-field v-bind="field" :label="t('fields.phone')" :error-messages="errors.phone" />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <Field v-slot="{ field }" name="currency">
                        <v-text-field
                          v-bind="field"
                          :label="`${t('settingsPage.fields.currency')} *`"
                          :error-messages="errors.currency"
                          maxlength="3"
                        />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <Field v-slot="{ field }" name="timezone">
                        <v-text-field v-bind="field" :label="`${t('settingsPage.fields.timezone')} *`" :error-messages="errors.timezone" />
                      </Field>
                    </v-col>
                    <v-col cols="12">
                      <Field v-slot="{ field }" name="address">
                        <v-textarea v-bind="field" :label="t('fields.address')" rows="2" :error-messages="errors.address" />
                      </Field>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-window-item>

            <v-window-item value="invoice">
              <v-card variant="outlined">
                <v-card-text>
                  <v-row>
                    <v-col cols="12" sm="4">
                      <Field v-slot="{ field }" name="invoice_prefix">
                        <v-text-field
                          v-bind="field"
                          :label="t('settingsPage.fields.invoicePrefix')"
                          :error-messages="errors.invoice_prefix"
                        />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="4">
                      <Field v-slot="{ field }" name="default_tax_rate">
                        <v-text-field
                          v-bind="field"
                          type="number"
                          :label="t('settingsPage.fields.defaultTaxRate')"
                          suffix="%"
                          :error-messages="errors.default_tax_rate"
                        />
                      </Field>
                    </v-col>
                    <v-col cols="12" sm="4">
                      <Field v-slot="{ field }" name="default_due_days">
                        <v-text-field
                          v-bind="field"
                          type="number"
                          :label="t('settingsPage.fields.defaultDueDays')"
                          :error-messages="errors.default_due_days"
                        />
                      </Field>
                    </v-col>
                    <v-col cols="12">
                      <Field v-slot="{ field }" name="invoice_footer">
                        <v-textarea
                          v-bind="field"
                          :label="t('settingsPage.fields.invoiceFooter')"
                          rows="3"
                          :error-messages="errors.invoice_footer"
                        />
                      </Field>
                    </v-col>
                  </v-row>
                </v-card-text>
              </v-card>
            </v-window-item>

            <v-window-item value="theme">
              <v-card variant="outlined">
                <v-card-text>
                  <v-row>
                    <v-col cols="12" sm="6">
                      <div class="d-flex align-center ga-3">
                        <input
                          type="color"
                          :value="values.primary_color || '#6750A4'"
                          style="width: 40px; height: 40px; border: none; padding: 0; background: none"
                          @input="setFieldValue('primary_color', $event.target.value)"
                        />
                        <Field v-slot="{ field }" name="primary_color">
                          <v-text-field
                            v-bind="field"
                            class="flex-grow-1"
                            :label="t('settingsPage.fields.primaryColor')"
                            :error-messages="errors.primary_color"
                            placeholder="#6750A4"
                            clearable
                          />
                        </Field>
                      </div>
                    </v-col>
                    <v-col cols="12" sm="6">
                      <div class="d-flex align-center ga-3">
                        <input
                          type="color"
                          :value="values.secondary_color || '#625B71'"
                          style="width: 40px; height: 40px; border: none; padding: 0; background: none"
                          @input="setFieldValue('secondary_color', $event.target.value)"
                        />
                        <Field v-slot="{ field }" name="secondary_color">
                          <v-text-field
                            v-bind="field"
                            class="flex-grow-1"
                            :label="t('settingsPage.fields.secondaryColor')"
                            :error-messages="errors.secondary_color"
                            placeholder="#625B71"
                            clearable
                          />
                        </Field>
                      </div>
                    </v-col>
                  </v-row>
                  <p class="text-body-2 text-medium-emphasis mb-0">{{ t('settingsPage.themeHint') }}</p>
                </v-card-text>
              </v-card>
            </v-window-item>

            <v-window-item value="data">
              <v-card variant="outlined">
                <v-card-text>
                  <p class="text-body-2 text-medium-emphasis">{{ t('settingsPage.dataExportHint') }}</p>
                  <v-btn
                    color="primary"
                    prepend-icon="mdi-download"
                    :loading="exporting"
                    @click="exportData"
                  >
                    {{ t('settingsPage.actions.exportData') }}
                  </v-btn>
                </v-card-text>
              </v-card>
            </v-window-item>
          </v-window>

          <div v-if="tab !== 'data'" class="d-flex justify-end mt-4">
            <v-btn type="submit" color="primary" :loading="saving">{{ t('settingsPage.actions.save') }}</v-btn>
          </div>
        </template>
      </AppForm>
    </template>
  </div>
</template>
