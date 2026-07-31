<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { planSchema } from '@/utils/validators'
import { createAdminPlanApi, updateAdminPlanApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  plan: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

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
  storage_limit_gb: props.plan?.storage_limit_gb ?? null,
  monthly_order_limit: props.plan?.monthly_order_limit ?? null,
  trial_days: props.plan?.trial_days ?? 14,
  has_watermark_gallery: props.plan?.has_watermark_gallery ?? true,
  has_online_gallery: props.plan?.has_online_gallery ?? true,
  has_reports: props.plan?.has_reports ?? false,
  has_api_access: props.plan?.has_api_access ?? false,
  is_active: props.plan?.is_active ?? true,
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

    <AppForm :id="formId" :schema="planSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
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
            <v-text-field :model-value="values.max_users" type="number" :label="t('admin.plans.fields.maxUsers')" :error-messages="errors.max_users" @update:model-value="setFieldValue('max_users', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.storage_limit_gb" type="number" :label="t('admin.plans.fields.storageLimitGb')" :error-messages="errors.storage_limit_gb" @update:model-value="setFieldValue('storage_limit_gb', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.monthly_order_limit" type="number" :label="t('admin.plans.fields.monthlyOrderLimit')" :error-messages="errors.monthly_order_limit" @update:model-value="setFieldValue('monthly_order_limit', $event)" />
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

          <v-col cols="12">
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
            </v-row>
          </v-col>
        </v-row>
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
