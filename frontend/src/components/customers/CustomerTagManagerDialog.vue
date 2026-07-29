<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import { customerTagSchema } from '@/utils/validators'
import { createCustomerTagApi, deleteCustomerTagApi } from '@/apis/customer-tag.api'
import { useCustomerTagsStore } from '@/stores/customerTags'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()
const tagsStore = useCustomerTagsStore()

const loading = ref(false)
const confirmDelete = ref(false)
const tagToDelete = ref(null)

watch(
  () => props.modelValue,
  (open) => {
    if (open) tagsStore.fetch(true)
  },
)

async function onSubmit(values, { resetForm }) {
  loading.value = true
  try {
    await createCustomerTagApi(values)
    resetForm()
    tagsStore.invalidate()
    await tagsStore.fetch(true)
    appStore.notify(t('customers.messages.tagCreatedSuccess'))
  } catch (error) {
    appStore.notify(error.response?.data?.message || t('customers.messages.tagCreateError'), 'error')
  } finally {
    loading.value = false
  }
}

function askDelete(tag) {
  tagToDelete.value = tag
  confirmDelete.value = true
}

async function confirmDeleteTag() {
  await deleteCustomerTagApi(tagToDelete.value.id)
  confirmDelete.value = false
  tagsStore.invalidate()
  await tagsStore.fetch(true)
  appStore.notify(t('customers.messages.tagDeletedSuccess'))
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('customers.dialogs.manageTagsTitle')"
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <AppForm :schema="customerTagSchema" :initial-values="{ name: '', color: '#6750A4' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <div class="d-flex ga-2 align-start mb-4">
          <v-text-field
            :model-value="values.name"
            :label="t('customers.dialogs.newTagNameLabel')"
            density="compact"
            hide-details
            :error-messages="errors.name"
            @update:model-value="setFieldValue('name', $event)"
          />
          <input
            :value="values.color"
            type="color"
            style="width: 40px; height: 40px; border: none; cursor: pointer"
            @input="setFieldValue('color', $event.target.value)"
          />
          <v-btn type="submit" icon="mdi-plus" color="primary" :loading="loading" />
        </div>
      </template>
    </AppForm>

    <v-list density="compact">
      <v-list-item v-for="tag in tagsStore.tags" :key="tag.id">
        <template #prepend>
          <v-avatar size="16" :color="tag.color" />
        </template>
        <v-list-item-title class="ml-2">{{ tag.name }}</v-list-item-title>
        <template #append>
          <v-btn icon="mdi-delete-outline" size="small" variant="text" @click="askDelete(tag)" />
        </template>
      </v-list-item>
    </v-list>

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('customers.dialogs.deleteTagConfirmTitle')"
      :message="t('customers.dialogs.deleteTagConfirmMessage', { name: tagToDelete?.name })"
      @confirm="confirmDeleteTag"
    />
  </AppDialog>
</template>
