<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import { expenseCategorySchema } from '@/utils/validators'
import { createExpenseCategoryApi, deleteExpenseCategoryApi } from '@/apis/expense-category.api'
import { useExpenseCategoriesStore } from '@/stores/expenseCategories'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()
const categoriesStore = useExpenseCategoriesStore()

const loading = ref(false)
const confirmDelete = ref(false)
const categoryToDelete = ref(null)

watch(() => props.modelValue, (open) => {
  if (open) categoriesStore.fetch(true)
})

async function onSubmit(values, { resetForm }) {
  loading.value = true
  try {
    await createExpenseCategoryApi(values)
    resetForm()
    categoriesStore.invalidate()
    await categoriesStore.fetch(true)
    appStore.notify(t('expenses.messages.categoryCreated'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'expenses.messages.categoryCreateError'), 'error')
  } finally {
    loading.value = false
  }
}

function askDelete(category) {
  categoryToDelete.value = category
  confirmDelete.value = true
}

async function confirmDeleteCategory() {
  await deleteExpenseCategoryApi(categoryToDelete.value.id)
  confirmDelete.value = false
  categoriesStore.invalidate()
  await categoriesStore.fetch(true)
  appStore.notify(t('expenses.messages.categoryDeleted'))
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('expenses.manageCategories')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppForm :schema="expenseCategorySchema" :initial-values="{ name: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <div class="d-flex ga-2 align-start mb-4">
          <v-text-field :model-value="values.name" :label="t('expenses.newCategoryName')" density="compact" hide-details :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          <v-btn type="submit" icon="mdi-plus" color="primary" :loading="loading" />
        </div>
      </template>
    </AppForm>

    <v-list density="compact">
      <v-list-item v-for="category in categoriesStore.categories" :key="category.id" :title="category.name">
        <template #append>
          <v-btn icon="mdi-delete-outline" size="small" variant="text" @click="askDelete(category)" />
        </template>
      </v-list-item>
    </v-list>

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('expenses.deleteCategoryTitle')"
      :message="t('expenses.deleteCategoryMessage', { name: categoryToDelete?.name })"
      @confirm="confirmDeleteCategory"
    />
  </AppDialog>
</template>
