<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { planFeatureListingSchema } from '@/utils/validators'
import { usePlanFeatureListingCatalogStore } from '@/stores/planFeatureListingCatalog'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  listing: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const catalogStore = usePlanFeatureListingCatalogStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const isEdit = computed(() => Boolean(props.listing?.id))
const title = computed(() => (isEdit.value ? t('admin.planFeatureListings.editFeature') : t('admin.planFeatureListings.newFeature')))

const initialValues = computed(() => ({
  key: props.listing?.key ?? '',
  label_en: props.listing?.label?.en ?? '',
  label_km: props.listing?.label?.km ?? '',
  value_type: props.listing?.value_type ?? 'text',
  sort_order: props.listing?.sort_order ?? 0,
  is_active: props.listing?.is_active ?? true,
}))

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await catalogStore.update(props.listing.id, values)
      appStore.notify(t('admin.planFeatureListings.messages.updatedSuccess'))
    } else {
      await catalogStore.create(values)
      appStore.notify(t('admin.planFeatureListings.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'admin.planFeatureListings.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="planFeatureListingSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12">
            <v-text-field
              :model-value="values.key"
              :label="`${t('admin.planFeatureListings.fields.key')} *`"
              :hint="t('admin.planFeatureListings.fields.keyHint')"
              persistent-hint
              :error-messages="errors.key"
              :disabled="isEdit"
              @update:model-value="setFieldValue('key', $event)"
            />
          </v-col>
          <v-col cols="12">
            <v-text-field
              :model-value="values.label_en"
              :label="`${t('admin.planFeatureListings.fields.labelEn')} *`"
              :error-messages="errors.label_en"
              @update:model-value="setFieldValue('label_en', $event)"
            />
          </v-col>
          <v-col cols="12">
            <v-text-field
              :model-value="values.label_km"
              :label="t('admin.planFeatureListings.fields.labelKm')"
              :error-messages="errors.label_km"
              @update:model-value="setFieldValue('label_km', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.value_type"
              :label="t('admin.planFeatureListings.fields.valueType')"
              :items="[
                { title: t('admin.planFeatureListings.valueTypes.text'), value: 'text' },
                { title: t('admin.planFeatureListings.valueTypes.boolean'), value: 'boolean' },
              ]"
              :error-messages="errors.value_type"
              @update:model-value="setFieldValue('value_type', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field
              :model-value="values.sort_order"
              type="number"
              :label="t('admin.planFeatureListings.fields.sortOrder')"
              :error-messages="errors.sort_order"
              @update:model-value="setFieldValue('sort_order', $event)"
            />
          </v-col>
          <v-col cols="12">
            <v-checkbox
              :model-value="values.is_active"
              :label="t('admin.planFeatureListings.fields.isActive')"
              hide-details
              @update:model-value="setFieldValue('is_active', $event)"
            />
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
