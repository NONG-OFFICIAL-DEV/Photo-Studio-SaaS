<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { inventoryItemSchema } from '@/utils/validators'
import { createInventoryItemApi, updateInventoryItemApi } from '@/apis/inventory.api'
import { useAppStore } from '@/stores/app'
import { useBranchStore } from '@/stores/branches'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const branchStore = useBranchStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const isEdit = computed(() => Boolean(props.item?.id))
const title = computed(() => (isEdit.value ? t('inventory.editItem') : t('inventory.newItem')))

// Common units for consumable studio stock (paper, ink, albums, ...) —
// picking from this list is the common case, but it's a combobox rather
// than a plain select so an item that doesn't fit (e.g. a specific
// cartridge size) can still have its own free-text unit.
const UNIT_OPTIONS = computed(() => [
  t('inventory.units.piece'),
  t('inventory.units.box'),
  t('inventory.units.pack'),
  t('inventory.units.roll'),
  t('inventory.units.sheet'),
  t('inventory.units.bottle'),
  t('inventory.units.set'),
  t('inventory.units.kilogram'),
  t('inventory.units.liter'),
  t('inventory.units.meter'),
])

const initialValues = computed(() => ({
  name: props.item?.name ?? '',
  branch_id: props.item?.branch_id ?? null,
  sku: props.item?.sku ?? '',
  unit: props.item?.unit ?? '',
  category: props.item?.category ?? '',
  reorder_threshold: props.item?.reorder_threshold ?? null,
  is_active: props.item?.is_active ?? true,
  initial_quantity: null,
}))

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    branchStore.fetch()
  }
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
    errorMessage.value = translateApiMessage(error, 'inventory.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="inventoryItemSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.sku" :label="t('inventory.sku')" :error-messages="errors.sku" @update:model-value="setFieldValue('sku', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-combobox
              :model-value="values.unit"
              :items="UNIT_OPTIONS"
              :label="`${t('inventory.unit')} *`"
              :hint="t('inventory.unitHint')"
              persistent-hint
              :error-messages="errors.unit"
              @update:model-value="setFieldValue('unit', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.category" :label="t('fields.category')" :error-messages="errors.category" @update:model-value="setFieldValue('category', $event)" />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.reorder_threshold" :label="t('inventory.reorderThreshold')" type="number" step="0.01" :error-messages="errors.reorder_threshold" @update:model-value="setFieldValue('reorder_threshold', $event)" />
          </v-col>
          <v-col v-if="branchStore.branches.length > 1" cols="12" sm="4">
            <v-select
              :model-value="values.branch_id"
              :label="`${t('fields.branch')} *`"
              :items="branchStore.branches"
              item-title="name"
              item-value="id"
              :error-messages="errors.branch_id"
              @update:model-value="setFieldValue('branch_id', $event)"
            />
          </v-col>
          <v-col v-if="!isEdit" cols="12" sm="4">
            <v-text-field
              :model-value="values.initial_quantity"
              :label="t('inventory.initialQuantity')"
              :hint="t('inventory.initialQuantityHint')"
              persistent-hint
              type="number"
              step="0.01"
              :error-messages="errors.initial_quantity"
              @update:model-value="setFieldValue('initial_quantity', $event)"
            />
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
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
