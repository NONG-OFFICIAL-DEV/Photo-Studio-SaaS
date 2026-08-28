<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { planSchema } from '@/utils/validators'
import { createAdminPlanApi, updateAdminPlanApi } from '@/apis/admin.api'
import { usePlanFeatureListingCatalogStore } from '@/stores/planFeatureListingCatalog'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  plan: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const catalogStore = usePlanFeatureListingCatalogStore()

watch(() => props.modelValue, (open) => {
  if (open) catalogStore.fetch()
})

const activeFeatures = computed(() => catalogStore.items.filter((item) => item.is_active))

// Plans saved before the catalog existed may still carry an old shape
// (array-of-rows, or the original fixed-key object) instead of the current
// object-keyed-by-PlanFeatureListing.key shape — treat anything that isn't
// a plain object as empty rather than crashing the edit form on it.
function normalizeFeatureLabels(raw) {
  return raw && typeof raw === 'object' && !Array.isArray(raw) ? raw : {}
}

function setFeatureValue(values, setFieldValue, key, value) {
  setFieldValue('feature_labels', { ...(values.feature_labels ?? {}), [key]: value })
}

function updateFeatureText(values, setFieldValue, key, lang, text) {
  const current = values.feature_labels?.[key] ?? { en: '', km: '' }
  setFeatureValue(values, setFieldValue, key, { ...current, [lang]: text })
}

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()
const activeTab = ref('details')
// The feature editor shows one language's value at a time (switched via
// this toggle) for text-type catalog features — both languages are still
// stored and saved together, just edited one at a time. Boolean-type
// features ignore this toggle entirely (a switch has no language).
const activeFeatureLocale = ref('en')

const isEdit = computed(() => Boolean(props.plan?.id))
const title = computed(() => (isEdit.value ? t('admin.plans.editPlan') : t('admin.plans.newPlan')))

const initialValues = computed(() => ({
  name: props.plan?.name ?? '',
  code: props.plan?.code ?? '',
  description: props.plan?.description ?? '',
  price_monthly: props.plan?.price_monthly ?? 0,
  price_quarterly: props.plan?.price_quarterly ?? null,
  price_yearly: props.plan?.price_yearly ?? null,
  max_users: props.plan?.max_users ?? null,
  max_branches: props.plan?.max_branches ?? null,
  storage_limit_gb: props.plan?.storage_limit_gb ?? null,
  monthly_order_limit: props.plan?.monthly_order_limit ?? null,
  trial_days: props.plan?.trial_days ?? 14,
  has_watermark_gallery: props.plan?.has_watermark_gallery ?? true,
  has_online_gallery: props.plan?.has_online_gallery ?? true,
  has_reports: props.plan?.has_reports ?? false,
  has_api_access: props.plan?.has_api_access ?? false,
  has_telegram: props.plan?.has_telegram ?? false,
  is_active: props.plan?.is_active ?? true,
  feature_labels: normalizeFeatureLabels(props.plan?.feature_labels),
  sort_order: props.plan?.sort_order ?? 0,
}))

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateAdminPlanApi(props.plan.id, values)
      appStore.notify(t('admin.plans.messages.updatedSuccess'))
    } else {
      await createAdminPlanApi(values)
      appStore.notify(t('admin.plans.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'admin.plans.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <v-tabs v-model="activeTab" density="compact" class="mb-4">
      <v-tab value="details">{{ t('admin.plans.tabs.details') }}</v-tab>
      <v-tab value="limits">{{ t('admin.plans.tabs.limits') }}</v-tab>
      <v-tab value="features">
        {{ t('admin.plans.tabs.features') }}
      </v-tab>
    </v-tabs>

    <AppForm :id="formId" :schema="planSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-window v-model="activeTab">
          <v-window-item value="details">
            <v-row class="mt-2">
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.code" :label="`${t('admin.plans.fields.code')} *`" :error-messages="errors.code" :disabled="isEdit" @update:model-value="setFieldValue('code', $event)" />
              </v-col>
              <v-col cols="12">
                <v-textarea :model-value="values.description" :label="t('fields.description')" rows="2" :error-messages="errors.description" @update:model-value="setFieldValue('description', $event)" />
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field :model-value="values.price_monthly" type="number" :label="t('admin.plans.fields.priceMonthly')" prefix="$" :error-messages="errors.price_monthly" @update:model-value="setFieldValue('price_monthly', $event)" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field :model-value="values.price_quarterly" type="number" :label="t('admin.plans.fields.priceQuarterly')" prefix="$" :error-messages="errors.price_quarterly" @update:model-value="setFieldValue('price_quarterly', $event)" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field :model-value="values.price_yearly" type="number" :label="t('admin.plans.fields.priceYearly')" prefix="$" :error-messages="errors.price_yearly" @update:model-value="setFieldValue('price_yearly', $event)" />
              </v-col>

              <v-col cols="12" sm="4">
                <v-text-field :model-value="values.trial_days" type="number" :label="t('admin.plans.fields.trialDays')" :error-messages="errors.trial_days" @update:model-value="setFieldValue('trial_days', $event)" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field :model-value="values.sort_order" type="number" :label="t('admin.plans.fields.sortOrder')" :error-messages="errors.sort_order" @update:model-value="setFieldValue('sort_order', $event)" />
              </v-col>
              <v-col cols="12" sm="4" class="d-flex align-center">
                <v-checkbox
                  :model-value="values.is_active"
                  :label="t('admin.plans.fields.isActive')"
                  hide-details
                  @update:model-value="setFieldValue('is_active', $event)"
                />
              </v-col>
            </v-row>
          </v-window-item>

          <v-window-item value="limits">
            <v-row class="mt-2">
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.max_users" type="number" :label="t('admin.plans.fields.maxUsers')" :error-messages="errors.max_users" @update:model-value="setFieldValue('max_users', $event)" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.max_branches" type="number" :label="t('admin.plans.fields.maxBranches')" :error-messages="errors.max_branches" @update:model-value="setFieldValue('max_branches', $event)" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.storage_limit_gb" type="number" :label="t('admin.plans.fields.storageLimitGb')" :error-messages="errors.storage_limit_gb" @update:model-value="setFieldValue('storage_limit_gb', $event)" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field :model-value="values.monthly_order_limit" type="number" :label="t('admin.plans.fields.monthlyOrderLimit')" :error-messages="errors.monthly_order_limit" @update:model-value="setFieldValue('monthly_order_limit', $event)" />
              </v-col>

              <v-col cols="12">
                <v-divider class="mb-2" />
                <v-row>
                  <v-col cols="6" sm="3">
                    <v-checkbox
                      :model-value="values.has_watermark_gallery"
                      :label="t('admin.plans.fields.hasWatermarkGallery')"
                      hide-details
                      @update:model-value="setFieldValue('has_watermark_gallery', $event)"
                    />
                  </v-col>
                  <v-col cols="6" sm="3">
                    <v-checkbox
                      :model-value="values.has_online_gallery"
                      :label="t('admin.plans.fields.hasOnlineGallery')"
                      hide-details
                      @update:model-value="setFieldValue('has_online_gallery', $event)"
                    />
                  </v-col>
                  <v-col cols="6" sm="3">
                    <v-checkbox
                      :model-value="values.has_reports"
                      :label="t('admin.plans.fields.hasReports')"
                      hide-details
                      @update:model-value="setFieldValue('has_reports', $event)"
                    />
                  </v-col>
                  <v-col cols="6" sm="3">
                    <v-checkbox
                      :model-value="values.has_api_access"
                      :label="t('admin.plans.fields.hasApiAccess')"
                      hide-details
                      @update:model-value="setFieldValue('has_api_access', $event)"
                    />
                  </v-col>
                  <v-col cols="6" sm="3">
                    <v-checkbox
                      :model-value="values.has_telegram"
                      :label="t('admin.plans.fields.hasTelegram')"
                      hide-details
                      @update:model-value="setFieldValue('has_telegram', $event)"
                    />
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-window-item>

          <v-window-item value="features">
            <p class="text-caption text-medium-emphasis mb-3">{{ t('admin.plans.featureLabelsHint') }}</p>

            <v-btn-toggle v-model="activeFeatureLocale" mandatory density="compact" color="primary" variant="outlined" class="mb-3">
              <v-btn value="en" size="small">EN</v-btn>
              <v-btn value="km" size="small">KM</v-btn>
            </v-btn-toggle>

            <p v-if="!activeFeatures.length" class="text-caption text-medium-emphasis">
              {{ t('admin.plans.noFeatureListings') }}
            </p>

            <v-row v-for="item in activeFeatures" :key="item.id" class="mb-1" dense align="center">
              <v-col cols="5" class="text-body-2">{{ item.label.en }}</v-col>
              <v-col cols="7">
                <v-switch
                  v-if="item.value_type === 'boolean'"
                  :model-value="values.feature_labels?.[item.key] ?? false"
                  density="compact"
                  hide-details
                  color="primary"
                  @update:model-value="setFeatureValue(values, setFieldValue, item.key, $event)"
                />
                <v-text-field
                  v-else
                  :model-value="values.feature_labels?.[item.key]?.[activeFeatureLocale] ?? ''"
                  density="compact"
                  hide-details
                  :placeholder="t('admin.plans.fields.featureValue')"
                  @update:model-value="updateFeatureText(values, setFieldValue, item.key, activeFeatureLocale, $event)"
                />
              </v-col>
            </v-row>
          </v-window-item>
        </v-window>
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
