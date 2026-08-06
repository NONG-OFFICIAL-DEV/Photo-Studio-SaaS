<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import PayrollFormDialog from '@/components/payroll/PayrollFormDialog.vue'
import { getPayrollEntriesApi, deletePayrollEntryApi, payPayrollEntryApi } from '@/apis/payroll.api'
import { translateApiMessage } from '@/utils/apiMessages'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tableRef = ref(null)

const STATUS_MAP = computed(() => ({
  draft: { color: 'default', label: t('payroll.status.draft') },
  paid: { color: 'success', label: t('payroll.status.paid') },
}))

const headers = computed(() => [
  { title: t('payroll.periodLabel'), key: 'period_label' },
  { title: t('fields.assignedTo'), key: 'user' },
  { title: t('payroll.basePay'), key: 'base_pay', sortable: false },
  { title: t('payroll.commissionTotal'), key: 'commission_total', sortable: false },
  { title: t('payroll.netPay'), key: 'net_pay', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null })

async function fetchEntries(params) {
  const { data } = await getPayrollEntriesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)
const actionLoading = ref(false)

function askDelete(entry) {
  deleteTarget.value = entry
  confirmDelete.value = true
}

async function confirmDeleteEntry() {
  await deletePayrollEntryApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('payroll.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

async function markPaid(entry) {
  actionLoading.value = true
  try {
    await payPayrollEntryApi(entry.id)
    appStore.notify(t('payroll.messages.paidSuccess'))
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const canCreate = computed(() => auth.hasPermission('payroll.create'))
const canDelete = computed(() => auth.hasPermission('payroll.delete'))
const canPay = computed(() => auth.hasPermission('payroll.pay'))
</script>

<template>
  <div>
    <div class="d-flex justify-end mb-2">
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="formDialog = true">{{ t('payroll.newEntry') }}</v-btn>
    </div>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          :label="t('fields.status')"
          clearable
          density="compact"
          :items="Object.entries(STATUS_MAP).map(([value, s]) => ({ title: s.label, value }))"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchEntries"
        :filters="filters"
        item-label="entries"
      >
        <template #[`item.user`]="{ item }">
          {{ item.user?.name }}
        </template>

        <template #[`item.base_pay`]="{ item }">
          ${{ item.base_pay }}
        </template>

        <template #[`item.commission_total`]="{ item }">
          ${{ item.commission_total }}
        </template>

        <template #[`item.net_pay`]="{ item }">
          <strong>${{ item.net_pay }}</strong>
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canPay && item.status === 'draft'" size="small" variant="tonal" color="success" class="mr-1" :loading="actionLoading" @click="markPaid(item)">
            {{ t('payroll.markPaid') }}
          </v-btn>
          <v-btn v-if="canDelete && item.status === 'draft'" icon="mdi-trash-can-outline" size="small" variant="text" color="error" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <PayrollFormDialog v-model="formDialog" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('payroll.deleteConfirmTitle')"
      :message="t('payroll.deleteConfirmMessage')"
      @confirm="confirmDeleteEntry"
    />
  </div>
</template>
