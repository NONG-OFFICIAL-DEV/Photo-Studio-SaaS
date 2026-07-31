<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import ExpenseFormDialog from '@/components/expenses/ExpenseFormDialog.vue'
import ExpenseCategoryManagerDialog from '@/components/expenses/ExpenseCategoryManagerDialog.vue'
import { getExpensesApi, deleteExpenseApi } from '@/apis/expense.api'
import { useExpenseCategoriesStore } from '@/stores/expenseCategories'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { formatDate } from '@/utils/dateFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const categoriesStore = useExpenseCategoriesStore()
const tableRef = ref(null)

categoriesStore.fetch()

const headers = computed(() => [
  { title: t('expenses.expenseDate'), key: 'expense_date' },
  { title: t('fields.category'), key: 'category' },
  { title: t('expenses.vendor'), key: 'vendor' },
  { title: t('invoices.methodLabel'), key: 'payment_method', sortable: false },
  { title: t('fields.total'), key: 'amount' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ category_id: null, payment_method: null, date_from: null, date_to: null })

async function fetchExpenses(params) {
  const { data } = await getExpensesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingExpense = ref(null)
const categoryManagerDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingExpense.value = null
  formDialog.value = true
}

function openEdit(expense) {
  editingExpense.value = expense
  formDialog.value = true
}

function askDelete(expense) {
  deleteTarget.value = expense
  confirmDelete.value = true
}

async function confirmDeleteExpense() {
  await deleteExpenseApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('expenses.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

const canCreate = computed(() => auth.hasPermission('expenses.create'))
const canUpdate = computed(() => auth.hasPermission('expenses.update'))
const canDelete = computed(() => auth.hasPermission('expenses.delete'))
</script>

<template>
  <div>
    <AppToolbar :title="t('expenses.title')" :subtitle="t('expenses.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" variant="outlined" prepend-icon="mdi-shape-outline" class="mr-2" @click="categoryManagerDialog = true">
          {{ t('expenses.manageCategories') }}
        </v-btn>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('expenses.newExpense') }}</v-btn>
      </template>
    </AppToolbar>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.category_id"
          :label="t('fields.category')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="categoriesStore.categories"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="filters.date_from" :label="t('expenses.dateFrom')" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="filters.date_to" :label="t('expenses.dateTo')" />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchExpenses"
        :filters="filters"
        item-label="expenses"
      >
        <template #[`item.expense_date`]="{ item }">
          {{ formatDate(item.expense_date) }}
        </template>

        <template #[`item.category`]="{ item }">
          {{ item.category?.name || '—' }}
        </template>

        <template #[`item.payment_method`]="{ item }">
          {{ t(`invoices.methods.${item.payment_method === 'bank_transfer' ? 'bankTransfer' : item.payment_method}`) }}
        </template>

        <template #[`item.amount`]="{ item }">
          ${{ item.amount }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
          <v-btn v-if="canDelete" icon="mdi-trash-can-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <ExpenseFormDialog v-model="formDialog" :expense="editingExpense" @saved="tableRef?.refresh()" />

    <ExpenseCategoryManagerDialog v-model="categoryManagerDialog" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('expenses.deleteConfirmTitle')"
      :message="t('expenses.deleteConfirmMessage', { vendor: deleteTarget?.vendor || t('expenses.title') })"
      @confirm="confirmDeleteExpense"
    />
  </div>
</template>
