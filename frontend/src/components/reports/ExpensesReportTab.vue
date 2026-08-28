<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ReportTrendChart from '@/components/reports/ReportTrendChart.vue'
import { getExpenseReportApi, exportExpenseReportApi } from '@/apis/report.api'
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
    const { data } = await getExpenseReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId })
    report.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.dateFrom, props.dateTo, props.branchId], load, { immediate: true })

const chartLabels = computed(() => (report.value?.breakdown ?? []).map((row) => row.period))
const chartDatasets = computed(() => [
  { label: t('reports.total'), data: (report.value?.breakdown ?? []).map((row) => row.total) },
])

async function exportReport(format) {
  const { data } = await exportExpenseReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId, format })
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.download = `expense-report.${format}`
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
      <v-card variant="flat" border rounded="lg" class="pa-4 mb-4">
        <div class="text-caption text-medium-emphasis">{{ t('reports.total') }}</div>
        <div class="text-h6 font-weight-bold">{{ formatCurrency(report.total) }}</div>
      </v-card>

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
              <th>{{ t('fields.category') }}</th>
              <th>{{ t('reports.value') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in report.by_category" :key="row.category">
              <td>{{ row.category }}</td>
              <td>{{ formatCurrency(row.amount) }}</td>
            </tr>
            <tr v-if="!report.by_category.length">
              <td colspan="2" class="text-center text-medium-emphasis py-4">{{ t('common.noData') }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </template>
  </div>
</template>
