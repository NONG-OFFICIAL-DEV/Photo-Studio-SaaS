<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import CommissionFormDialog from '@/components/commissions/CommissionFormDialog.vue'
import { getCommissionEntriesApi, deleteCommissionEntryApi } from '@/apis/commission.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { formatDate } from '@/utils/dateFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tableRef = ref(null)

const headers = computed(() => [
  { title: t('commissions.earnedDate'), key: 'earned_date' },
  { title: t('fields.assignedTo'), key: 'user' },
  { title: t('invoices.amount'), key: 'amount' },
  { title: t('fields.notes'), key: 'notes', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({})

async function fetchEntries(params) {
  const { data } = await getCommissionEntriesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function askDelete(entry) {
  deleteTarget.value = entry
  confirmDelete.value = true
}

async function confirmDeleteEntry() {
  await deleteCommissionEntryApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('commissions.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

const canRecord = computed(() => auth.hasPermission('commissions.record'))
const canDelete = computed(() => auth.hasPermission('commissions.delete'))
</script>

<template>
  <div>
    <div class="d-flex justify-end mb-2">
      <v-btn v-if="canRecord" color="primary" prepend-icon="mdi-plus" @click="formDialog = true">{{ t('commissions.newEntry') }}</v-btn>
    </div>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchEntries"
        :filters="filters"
        item-label="entries"
      >
        <template #[`item.earned_date`]="{ item }">
          {{ formatDate(item.earned_date) }}
        </template>

        <template #[`item.user`]="{ item }">
          {{ item.user?.name }}
        </template>

        <template #[`item.amount`]="{ item }">
          ${{ item.amount }}
        </template>

        <template #[`item.notes`]="{ item }">
          {{ item.notes || '—' }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canDelete" icon="mdi-trash-can-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <CommissionFormDialog v-model="formDialog" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('commissions.deleteConfirmTitle')"
      :message="t('commissions.deleteConfirmMessage')"
      @confirm="confirmDeleteEntry"
    />
  </div>
</template>
