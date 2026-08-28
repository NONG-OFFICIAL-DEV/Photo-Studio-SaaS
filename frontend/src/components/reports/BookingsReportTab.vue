<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ReportTrendChart from '@/components/reports/ReportTrendChart.vue'
import { getBookingsReportApi, exportBookingsReportApi } from '@/apis/report.api'

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
    const { data } = await getBookingsReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId })
    report.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.dateFrom, props.dateTo, props.branchId], load, { immediate: true })

const chartLabels = computed(() => (report.value?.breakdown ?? []).map((row) => row.period))
const chartDatasets = computed(() => [
  { label: t('reports.total'), data: (report.value?.breakdown ?? []).map((row) => row.count) },
])

async function exportReport(format) {
  const { data } = await exportBookingsReportApi({ date_from: props.dateFrom, date_to: props.dateTo, branch_id: props.branchId, format })
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.download = `bookings-report.${format}`
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
        <div class="text-h6 font-weight-bold">{{ report.total }}</div>
      </v-card>

      <v-card variant="flat" border rounded="lg" class="mb-4">
        <v-card-title>{{ t('reports.trend') }}</v-card-title>
        <v-card-text style="height: 280px">
          <ReportTrendChart :labels="chartLabels" :datasets="chartDatasets" />
        </v-card-text>
      </v-card>

      <v-row>
        <v-col cols="12" md="6">
          <v-card variant="flat" border rounded="lg" class="pa-4">
            <div class="text-subtitle-2 mb-2">{{ t('reports.byType') }}</div>
            <v-table density="compact">
              <tbody>
                <tr v-for="row in report.by_type" :key="row.type">
                  <td>{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
        <v-col cols="12" md="6">
          <v-card variant="flat" border rounded="lg" class="pa-4">
            <div class="text-subtitle-2 mb-2">{{ t('reports.byStatus') }}</div>
            <v-table density="compact">
              <tbody>
                <tr v-for="row in report.by_status" :key="row.status">
                  <td>{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </div>
</template>
