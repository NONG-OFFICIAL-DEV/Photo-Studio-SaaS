<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { getExpenseReportApi, exportExpenseReportApi } from '@/apis/report.api'
import { formatCurrency } from '@/utils/currencyFormat'

const { t } = useI18n()

const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10))
const loading = ref(false)
const report = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await getExpenseReportApi({ date_from: dateFrom.value, date_to: dateTo.value })
    report.value = data.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function exportReport(format) {
  const { data } = await exportExpenseReportApi({ date_from: dateFrom.value, date_to: dateTo.value, format })
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.download = `expense-report.${format}`
  link.click()
  window.URL.revokeObjectURL(url)
}
</script>

<template>
  <div>
    <v-row dense class="mb-2">
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateFrom" :label="t('reports.dateFrom')" :clearable="false" @update:model-value="load" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateTo" :label="t('reports.dateTo')" :clearable="false" @update:model-value="load" />
      </v-col>
      <v-col cols="12" sm="6" class="d-flex justify-end ga-2 align-start">
        <v-btn variant="outlined" prepend-icon="mdi-file-delimited-outline" @click="exportReport('csv')">{{ t('reports.exportCsv') }}</v-btn>
        <v-btn variant="outlined" prepend-icon="mdi-file-excel-outline" @click="exportReport('xlsx')">{{ t('reports.exportExcel') }}</v-btn>
      </v-col>
    </v-row>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="report">
      <v-card variant="flat" border rounded="lg" class="pa-4 mb-4">
        <div class="text-caption text-medium-emphasis">{{ t('reports.total') }}</div>
        <div class="text-h6 font-weight-bold">{{ formatCurrency(report.total) }}</div>
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
