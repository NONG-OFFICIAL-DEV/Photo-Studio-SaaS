<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import AppSelectQuickAdd from '@/components/common/AppSelectQuickAdd.vue'
import { expenseSchema } from '@/utils/validators'
import { createExpenseApi, updateExpenseApi } from '@/apis/expense.api'
import { createExpenseCategoryApi } from '@/apis/expense-category.api'
import { useExpenseCategoriesStore } from '@/stores/expenseCategories'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  expense: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const categoriesStore = useExpenseCategoriesStore()

const loading = ref(false)
const errorMessage = ref('')

const isEdit = computed(() => Boolean(props.expense?.id))
const title = computed(() => (isEdit.value ? t('expenses.editExpense') : t('expenses.newExpense')))

const METHOD_ITEMS = computed(() => [
  { title: t('invoices.methods.cash'), value: 'cash' },
  { title: t('invoices.methods.bankTransfer'), value: 'bank_transfer' },
  { title: t('invoices.methods.card'), value: 'card' },
  { title: t('invoices.methods.other'), value: 'other' },
])

const initialValues = computed(() => ({
  category_id: props.expense?.category?.id ?? null,
  amount: props.expense?.amount ?? null,
  expense_date: props.expense?.expense_date ?? new Date().toISOString().slice(0, 10),
  vendor: props.expense?.vendor ?? '',
  payment_method: props.expense?.payment_method ?? 'cash',
  notes: props.expense?.notes ?? '',
}))

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    categoriesStore.fetch()
  }
})

async function createCategory({ name }) {
  try {
    const { data } = await createExpenseCategoryApi({ name })
    categoriesStore.invalidate()
    await categoriesStore.fetch(true)
    appStore.notify(t('expenses.messages.categoryCreated'))
    return data.data
  } catch (error) {
    throw new Error(translateApiMessage(error, 'expenses.messages.categoryCreateError'), { cause: error })
  }
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateExpenseApi(props.expense.id, values)
      appStore.notify(t('expenses.messages.updatedSuccess'))
    } else {
      await createExpenseApi(values)
      appStore.notify(t('expenses.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'expenses.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="expenseSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.amount" :label="`${t('fields.total')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.amount" @update:model-value="setFieldValue('amount', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <AppDatePicker
              :model-value="values.expense_date"
              :label="`${t('expenses.expenseDate')} *`"
              :error-messages="errors.expense_date"
              :clearable="false"
              @update:model-value="setFieldValue('expense_date', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <AppSelectQuickAdd
              :model-value="values.category_id"
              :label="t('fields.category')"
              :items="categoriesStore.categories"
              :add-label="t('common.addNewItem', { item: t('fields.category') })"
              :name-placeholder="t('expenses.newCategoryName')"
              :create-fn="createCategory"
              @update:model-value="setFieldValue('category_id', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.payment_method"
              :label="`${t('invoices.methodLabel')} *`"
              :items="METHOD_ITEMS"
              :error-messages="errors.payment_method"
              @update:model-value="setFieldValue('payment_method', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.vendor" :label="t('expenses.vendor')" :error-messages="errors.vendor" @update:model-value="setFieldValue('vendor', $event)" />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.notes" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" @update:model-value="setFieldValue('notes', $event)" />
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
