<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { expenseSchema } from '@/utils/validators'
import { createExpenseApi, updateExpenseApi } from '@/apis/expense.api'
import { useExpenseCategoriesStore } from '@/stores/expenseCategories'
import { useAppStore } from '@/stores/app'

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
    errorMessage.value = error.response?.data?.message || t('expenses.messages.saveError')
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
            <Field v-slot="{ field }" name="amount">
              <v-text-field v-bind="field" :label="`${t('fields.total')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.amount" />
            </Field>
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
            <Field v-slot="{ field }" name="vendor">
              <v-text-field v-bind="field" :label="t('expenses.vendor')" :error-messages="errors.vendor" />
            </Field>
          </v-col>
          <v-col cols="12">
            <Field v-slot="{ field }" name="notes">
              <v-textarea v-bind="field" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" />
            </Field>
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
