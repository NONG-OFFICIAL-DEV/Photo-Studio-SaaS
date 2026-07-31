<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import AttendanceFormDialog from '@/components/attendance/AttendanceFormDialog.vue'
import { getAttendanceRecordsApi, deleteAttendanceRecordApi } from '@/apis/attendance.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { formatDate } from '@/utils/dateFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tableRef = ref(null)

const STATUS_MAP = computed(() => ({
  present: { color: 'success', label: t('attendance.status.present') },
  late: { color: 'warning', label: t('attendance.status.late') },
  absent: { color: 'error', label: t('attendance.status.absent') },
}))

const headers = computed(() => [
  { title: t('fields.startDate'), key: 'date' },
  { title: t('fields.assignedTo'), key: 'user' },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('attendance.hoursWorked'), key: 'hours_worked', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null, date_from: null, date_to: null })

async function fetchRecords(params) {
  const { data } = await getAttendanceRecordsApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function askDelete(record) {
  deleteTarget.value = record
  confirmDelete.value = true
}

async function confirmDeleteRecord() {
  await deleteAttendanceRecordApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('attendance.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

const canManage = computed(() => auth.hasPermission('attendance.manage'))
</script>

<template>
  <div>
    <div class="d-flex justify-end mb-2">
      <v-btn v-if="canManage" color="primary" prepend-icon="mdi-plus" @click="formDialog = true">{{ t('attendance.newRecord') }}</v-btn>
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
        :fetch-fn="fetchRecords"
        :filters="filters"
        item-label="records"
      >
        <template #[`item.date`]="{ item }">
          {{ formatDate(item.date) }}
        </template>

        <template #[`item.user`]="{ item }">
          {{ item.user?.name }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.hours_worked`]="{ item }">
          {{ item.hours_worked ?? '—' }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canManage" icon="mdi-trash-can-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <AttendanceFormDialog v-model="formDialog" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('attendance.deleteConfirmTitle')"
      :message="t('attendance.deleteConfirmMessage')"
      @confirm="confirmDeleteRecord"
    />
  </div>
</template>
