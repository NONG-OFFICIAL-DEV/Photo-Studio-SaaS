<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import ReportFilterBar from '@/components/reports/ReportFilterBar.vue'
import RevenueReportTab from '@/components/reports/RevenueReportTab.vue'
import BookingsReportTab from '@/components/reports/BookingsReportTab.vue'
import OrdersReportTab from '@/components/reports/OrdersReportTab.vue'
import ExpensesReportTab from '@/components/reports/ExpensesReportTab.vue'

const { t } = useI18n()
const tab = ref('revenue')

// One shared date-range/branch filter for all four reports, set once
// above the tabs instead of duplicated per tab — each tab just receives
// these as props and reloads when they change (see *ReportTab.vue).
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10))
const branchId = ref(null)

const revenueTab = ref(null)
const bookingsTab = ref(null)
const ordersTab = ref(null)
const expensesTab = ref(null)

// Export always applies to whichever report is currently visible — each
// tab exposes its own exportReport() (format/filename differ per report),
// the filter bar just triggers it on the active one.
function exportActiveReport(format) {
  const activeTab = { revenue: revenueTab, bookings: bookingsTab, orders: ordersTab, expenses: expensesTab }[tab.value]
  activeTab?.value?.exportReport(format)
}
</script>

<template>
  <div>
    <AppToolbar :title="t('reports.title')" :subtitle="t('reports.subtitle')">
      <template #actions>
        <v-btn color="info" variant="outlined" prepend-icon="mdi-file-delimited-outline" @click="exportActiveReport('csv')">{{
          t('reports.exportCsv')
        }}</v-btn>
        <v-btn color="excel" variant="outlined" prepend-icon="mdi-file-excel-outline" @click="exportActiveReport('xlsx')">{{
          t('reports.exportExcel')
        }}</v-btn>
      </template>
    </AppToolbar>

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="revenue">{{ t('reports.revenue') }}</v-tab>
      <v-tab value="bookings">{{ t('reports.bookings') }}</v-tab>
      <v-tab value="orders">{{ t('reports.orders') }}</v-tab>
      <v-tab value="expenses">{{ t('reports.expenses') }}</v-tab>
    </v-tabs>

    <ReportFilterBar
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      v-model:branch-id="branchId"
    />

    <v-window v-model="tab">
      <v-window-item value="revenue" class="mt-2">
        <RevenueReportTab ref="revenueTab" :date-from="dateFrom" :date-to="dateTo" :branch-id="branchId" />
      </v-window-item>
      <v-window-item value="bookings" class="mt-2">
        <BookingsReportTab ref="bookingsTab" :date-from="dateFrom" :date-to="dateTo" :branch-id="branchId" />
      </v-window-item>
      <v-window-item value="orders" class="mt-2">
        <OrdersReportTab ref="ordersTab" :date-from="dateFrom" :date-to="dateTo" :branch-id="branchId" />
      </v-window-item>
      <v-window-item value="expenses" class="mt-2">
        <ExpensesReportTab ref="expensesTab" :date-from="dateFrom" :date-to="dateTo" :branch-id="branchId" />
      </v-window-item>
    </v-window>
  </div>
</template>
