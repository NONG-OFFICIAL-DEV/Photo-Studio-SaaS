<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { inventoryItemSchema } from '@/utils/validators'
import { createInventoryItemApi, updateInventoryItemApi } from '@/apis/inventory.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')

const isEdit = computed(() => Boolean(props.item?.id))
const title = computed(() => (isEdit.value ? t('inventory.editItem') : t('inventory.newItem')))

const initialValues = computed(() => ({
  name: props.item?.name ?? '',
  sku: props.item?.sku ?? '',
  unit: props.item?.unit ?? 'unit',
  category: props.item?.category ?? '',
  reorder_threshold: props.item?.reorder_threshold ?? null,
  is_active: props.item?.is_active ?? true,
}))

watch(() => props.modelValue, (open) => {
  if (open) errorMessage.value = ''
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateInventoryItemApi(props.item.id, values)
      appStore.notify(t('inventory.messages.updatedSuccess'))
    } else {
      await createInventoryItemApi(values)
      appStore.notify(t('inventory.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('inventory.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="inventoryItemSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.sku" :label="t('inventory.sku')" :error-messages="errors.sku" @update:model-value="setFieldValue('sku', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.unit" :label="`${t('inventory.unit')} *`" :error-messages="errors.unit" @update:model-value="setFieldValue('unit', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.category" :label="t('fields.category')" :error-messages="errors.category" @update:model-value="setFieldValue('category', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.reorder_threshold" :label="t('inventory.reorderThreshold')" type="number" step="0.01" :error-messages="errors.reorder_threshold" @update:model-value="setFieldValue('reorder_threshold', $event)" />
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
