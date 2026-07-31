<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTable from '@/components/common/AppTable.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import AuditPropertiesDialog from '@/components/audit/AuditPropertiesDialog.vue'
import { formatDateTime } from '@/utils/dateFormat'

const props = defineProps({
  fetchFn: { type: Function, required: true },
  showLogName: { type: Boolean, default: false },
  showOutcome: { type: Boolean, default: false },
  extraFilters: { type: Object, default: () => ({}) },
})

const { t } = useI18n()

const dateFrom = ref(null)
const dateTo = ref(null)

const filters = computed(() => ({
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
  ...props.extraFilters,
}))

const headers = computed(() => [
  { title: t('auditPage.date'), key: 'created_at', sortable: false },
  ...(props.showLogName ? [{ title: t('auditPage.module'), key: 'log_name', sortable: false }] : []),
  ...(props.showOutcome ? [{ title: t('auditPage.outcome'), key: 'outcome', sortable: false }] : []),
  { title: t('auditPage.description'), key: 'description', sortable: false },
  { title: t('auditPage.causer'), key: 'causer', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function fetchEntries(params) {
  const { data } = await props.fetchFn(params)
  return { items: data.data, total: data.meta.total }
}

const detailsDialog = ref(false)
const selectedEntry = ref(null)

function showDetails(entry) {
  selectedEntry.value = entry
  detailsDialog.value = true
}
</script>

<template>
  <div>
    <v-row dense class="mb-2">
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateFrom" :label="t('reports.dateFrom')" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateTo" :label="t('reports.dateTo')" />
      </v-col>
    </v-row>

    <AppTable :headers="headers" :fetch-fn="fetchEntries" :filters="filters" item-label="entries">
      <template #[`item.created_at`]="{ item }">
        {{ formatDateTime(item.created_at) }}
      </template>

      <template #[`item.outcome`]="{ item }">
        <v-chip v-if="item.properties?.success" color="success" size="small" variant="tonal">{{ t('auditPage.success') }}</v-chip>
        <v-chip v-else color="error" size="small" variant="tonal">{{ t('auditPage.failed') }}</v-chip>
      </template>

      <template #[`item.causer`]="{ item }">
        <span v-if="item.causer">{{ item.causer.name }}</span>
        <span v-else class="text-medium-emphasis">{{ t('auditPage.unknown') }}</span>
      </template>

      <template #[`item.actions`]="{ item }">
        <v-btn size="small" variant="text" prepend-icon="mdi-information-outline" @click="showDetails(item)">
          {{ t('auditPage.details') }}
        </v-btn>
      </template>
    </AppTable>

    <AuditPropertiesDialog v-model="detailsDialog" :entry="selectedEntry" />
  </div>
</template>
