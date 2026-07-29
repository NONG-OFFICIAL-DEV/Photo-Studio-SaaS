<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import RevenueReportTab from '@/components/reports/RevenueReportTab.vue'
import BookingsReportTab from '@/components/reports/BookingsReportTab.vue'
import OrdersReportTab from '@/components/reports/OrdersReportTab.vue'
import ExpensesReportTab from '@/components/reports/ExpensesReportTab.vue'

const { t } = useI18n()
const tab = ref('revenue')
</script>

<template>
  <div>
    <AppToolbar :title="t('reports.title')" :subtitle="t('reports.subtitle')" />

    <v-tabs v-model="tab" class="mb-4">
      <v-tab value="revenue">{{ t('reports.revenue') }}</v-tab>
      <v-tab value="bookings">{{ t('reports.bookings') }}</v-tab>
      <v-tab value="orders">{{ t('reports.orders') }}</v-tab>
      <v-tab value="expenses">{{ t('reports.expenses') }}</v-tab>
    </v-tabs>

    <v-window v-model="tab">
      <v-window-item value="revenue"><RevenueReportTab /></v-window-item>
      <v-window-item value="bookings"><BookingsReportTab /></v-window-item>
      <v-window-item value="orders"><OrdersReportTab /></v-window-item>
      <v-window-item value="expenses"><ExpensesReportTab /></v-window-item>
    </v-window>
  </div>
</template>
