<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ReportTrendChart from '@/components/reports/ReportTrendChart.vue'
import { getOrdersReportApi, exportOrdersReportApi } from '@/apis/report.api'
import { formatCurrency } from '@/utils/currencyFormat'

const props = defineProps({
  dateFrom: { type: String, required: true },
  dateTo: { type: String, required: true },
  branchId: { type: String, default: null },
})

const { t } = useI18n()

const loading = ref(false)
const report = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await getOrdersReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId })
    report.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.dateFrom, props.dateTo, props.branchId], load, { immediate: true })

const chartLabels = computed(() => (report.value?.breakdown ?? []).map((row) => row.period))
const chartDatasets = computed(() => [
  { label: t('reports.totalValue'), data: (report.value?.breakdown ?? []).map((row) => row.value) },
])

async function exportReport(format) {
  const { data } = await exportOrdersReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId, format })
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.download = `orders-report.${format}`
  link.click()
  window.URL.revokeObjectURL(url)
}

defineExpose({ exportReport })
</script>

<template>
  <div>
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="report">
      <v-row class="mb-2">
        <v-col cols="12" sm="6">
          <v-card variant="flat" border rounded="lg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalCount') }}</div>
              <div class="text-h6 font-weight-bold">{{ report.total_count }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6">
          <v-card variant="flat" border rounded="lg">
            <v-card-text>
              <div class="text-caption text-medium-emphasis">{{ t('reports.totalValue') }}</div>
              <div class="text-h6 font-weight-bold">{{ formatCurrency(report.total_value) }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card variant="flat" border rounded="lg" class="mb-4">
        <v-card-title>{{ t('reports.trend') }}</v-card-title>
        <v-card-text style="height: 280px">
          <ReportTrendChart :labels="chartLabels" :datasets="chartDatasets" :value-formatter="formatCurrency" />
        </v-card-text>
      </v-card>

      <v-card variant="flat" border rounded="lg" class="pa-4">
        <v-table density="compact">
          <thead>
            <tr>
              <th>{{ t('fields.status') }}</th>
              <th>{{ t('reports.count') }}</th>
              <th>{{ t('reports.value') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.by_status" :key="row.status">
              <td>{{ row.label }}</td>
              <td>{{ row.count }}</td>
              <td>{{ formatCurrency(row.value) }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </template>
  </div>
</template>
