<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppSelectQuickAdd from '@/components/common/AppSelectQuickAdd.vue'
import { serviceSchema } from '@/utils/validators'
import { createServiceApi, updateServiceApi } from '@/apis/service.api'
import { createServiceCategoryApi } from '@/apis/service-category.api'
import { useServiceCategoriesStore } from '@/stores/serviceCategories'
import { translateApiMessage } from '@/utils/apiMessages'
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

async function createCategory({ name, description }) {
  try {
    const { data } = await createServiceCategoryApi({ name, description })
    categoriesStore.invalidate()
    await categoriesStore.fetch(true)
    appStore.notify(t('services.messages.categoryCreated'))
    return data.data
  } catch (error) {
    throw new Error(translateApiMessage(error, 'services.messages.categoryCreateError'), { cause: error })
  }
}

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
    errorMessage.value = translateApiMessage(error, 'services.messages.serviceSaveError')
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
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <AppSelectQuickAdd
              :model-value="values.category_id"
              :label="t('fields.category')"
              :items="categoriesStore.categories"
              :add-label="t('common.addNewItem', { item: t('fields.category') })"
              :name-placeholder="t('services.newCategoryName')"
              :description-placeholder="t('fields.description')"
              :create-fn="createCategory"
              @update:model-value="setFieldValue('category_id', $event)"
            />
          </v-col>

          <v-col cols="6" sm="4">
            <v-text-field :model-value="values.price" :label="`${t('fields.price')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.price" @update:model-value="setFieldValue('price', $event)" />
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
            <v-text-field :model-value="values.duration_minutes" :label="t('services.durationMinutes')" type="number" :error-messages="errors.duration_minutes" @update:model-value="setFieldValue('duration_minutes', $event)" />
          </v-col>

          <v-col cols="12">
            <v-textarea :model-value="values.description" :label="t('fields.description')" rows="2" :error-messages="errors.description" @update:model-value="setFieldValue('description', $event)" />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.deliverables" :label="t('services.deliverables')" rows="2" :placeholder="t('services.deliverablesPlaceholder')" :error-messages="errors.deliverables" @update:model-value="setFieldValue('deliverables', $event)" />
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
