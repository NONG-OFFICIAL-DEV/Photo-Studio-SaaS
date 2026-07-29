<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { serviceSchema } from '@/utils/validators'
import { createServiceApi, updateServiceApi } from '@/apis/service.api'
import { useServiceCategoriesStore } from '@/stores/serviceCategories'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  service: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const categoriesStore = useServiceCategoriesStore()

const loading = ref(false)
const errorMessage = ref('')

const PRICING_UNITS = computed(() => [
  { title: t('services.pricingUnits.fixed'), value: 'fixed' },
  { title: t('services.pricingUnits.perHour'), value: 'per_hour' },
  { title: t('services.pricingUnits.perPerson'), value: 'per_person' },
  { title: t('services.pricingUnits.perPhoto'), value: 'per_photo' },
])

const isEdit = computed(() => Boolean(props.service?.id))
const title = computed(() => (isEdit.value ? t('services.editService') : t('services.newService')))

const initialValues = computed(() => ({
  category_id: props.service?.category?.id ?? null,
  name: props.service?.name ?? '',
  description: props.service?.description ?? '',
  deliverables: props.service?.deliverables ?? '',
  price: props.service?.price ?? null,
  pricing_unit: props.service?.pricing_unit ?? 'fixed',
  duration_minutes: props.service?.duration_minutes ?? null,
  is_active: props.service?.is_active ?? true,
}))

watch(() => props.modelValue, (open) => {
  if (open) categoriesStore.fetch()
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateServiceApi(props.service.id, values)
      appStore.notify(t('services.messages.serviceUpdated'))
    } else {
      await createServiceApi(values)
      appStore.notify(t('services.messages.serviceCreated'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('services.messages.serviceSaveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="serviceSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="name">
              <v-text-field v-bind="field" :label="`${t('fields.name')} *`" :error-messages="errors.name" />
            </Field>
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.category_id"
              :label="t('fields.category')"
              clearable
              item-title="name"
              item-value="id"
              :items="categoriesStore.categories"
              @update:model-value="setFieldValue('category_id', $event)"
            />
          </v-col>

          <v-col cols="6" sm="4">
            <Field v-slot="{ field }" name="price">
              <v-text-field v-bind="field" :label="`${t('fields.price')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.price" />
            </Field>
          </v-col>
          <v-col cols="6" sm="4">
            <v-select
              :model-value="values.pricing_unit"
              :label="`${t('services.pricingUnit')} *`"
              :items="PRICING_UNITS"
              :error-messages="errors.pricing_unit"
              @update:model-value="setFieldValue('pricing_unit', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <Field v-slot="{ field }" name="duration_minutes">
              <v-text-field v-bind="field" :label="t('services.durationMinutes')" type="number" :error-messages="errors.duration_minutes" />
            </Field>
          </v-col>

          <v-col cols="12">
            <Field v-slot="{ field }" name="description">
              <v-textarea v-bind="field" :label="t('fields.description')" rows="2" :error-messages="errors.description" />
            </Field>
          </v-col>
          <v-col cols="12">
            <Field v-slot="{ field }" name="deliverables">
              <v-textarea v-bind="field" :label="t('services.deliverables')" rows="2" :placeholder="t('services.deliverablesPlaceholder')" :error-messages="errors.deliverables" />
            </Field>
          </v-col>

          <v-col cols="12">
            <v-switch
              :model-value="values.is_active"
              :label="t('services.activeSwitchLabel')"
              color="primary"
              hide-details
              @update:model-value="setFieldValue('is_active', $event)"
            />
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
