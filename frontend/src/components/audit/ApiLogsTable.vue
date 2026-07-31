<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTable from '@/components/common/AppTable.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { formatDateTime } from '@/utils/dateFormat'

const props = defineProps({
  fetchFn: { type: Function, required: true },
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
  { title: t('auditPage.method'), key: 'method', sortable: false },
  { title: t('auditPage.path'), key: 'path', sortable: false },
  { title: t('auditPage.status'), key: 'status_code', sortable: false },
  { title: t('auditPage.duration'), key: 'duration_ms', sortable: false },
  { title: t('auditPage.user'), key: 'user', sortable: false },
  { title: t('auditPage.ip'), key: 'ip', sortable: false },
])

async function fetchLogs(params) {
  const { data } = await props.fetchFn(params)
  return { items: data.data, total: data.meta.total }
}

const METHOD_COLORS = { GET: 'info', POST: 'success', PUT: 'warning', PATCH: 'warning', DELETE: 'error' }
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

    <AppTable :headers="headers" :fetch-fn="fetchLogs" :filters="filters" item-label="logs">
      <template #[`item.created_at`]="{ item }">
        {{ formatDateTime(item.created_at) }}
      </template>

      <template #[`item.method`]="{ item }">
        <v-chip :color="METHOD_COLORS[item.method] ?? 'grey'" size="small" variant="tonal" label>{{ item.method }}</v-chip>
      </template>

      <template #[`item.status_code`]="{ item }">
        <v-chip :color="item.status_code >= 400 ? 'error' : 'success'" size="small" variant="tonal">{{ item.status_code }}</v-chip>
      </template>

      <template #[`item.duration_ms`]="{ item }">
        {{ item.duration_ms }} ms
      </template>

      <template #[`item.user`]="{ item }">
        <span v-if="item.user">{{ item.user.name }}</span>
        <span v-else class="text-medium-emphasis">{{ t('auditPage.unknown') }}</span>
      </template>
    </AppTable>
  </div>
</template>
