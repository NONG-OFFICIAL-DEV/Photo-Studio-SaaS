<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
} from 'chart.js'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { getAdminAnalyticsApi } from '@/apis/admin.api'
import { formatCurrency } from '@/utils/currencyFormat'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const { t } = useI18n()
const statsData = ref(null)
const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth() - 5, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10))

const stats = computed(() => {
  const d = statsData.value
  return [
    { title: t('admin.analytics.totalTenants'), value: String(d?.total_tenants ?? 0), icon: 'mdi-domain', color: 'primary' },
    { title: t('admin.analytics.activeTenants'), value: String(d?.active_tenants ?? 0), icon: 'mdi-check-circle-outline', color: 'success' },
    { title: t('admin.analytics.suspendedTenants'), value: String(d?.suspended_tenants ?? 0), icon: 'mdi-cancel', color: 'error' },
    { title: t('admin.analytics.mrr'), value: formatCurrency(d?.mrr ?? 0), icon: 'mdi-cash-multiple', color: 'tertiary' },
  ]
})

const periodStats = computed(() => {
  const d = statsData.value
  return [
    { title: t('admin.analytics.newTenants'), value: String(d?.new_tenants ?? 0), icon: 'mdi-domain-plus', color: 'info' },
    { title: t('admin.analytics.revenueCollected'), value: formatCurrency(d?.revenue_collected ?? 0), icon: 'mdi-cash-check', color: 'success' },
  ]
})

const subscriptionRows = computed(() => {
  const s = statsData.value?.subscriptions_by_status ?? {}
  return [
    { key: 'trial', label: t('admin.analytics.statuses.trial'), value: s.trial ?? 0, color: 'info' },
    { key: 'active', label: t('admin.analytics.statuses.active'), value: s.active ?? 0, color: 'success' },
    { key: 'expired', label: t('admin.analytics.statuses.expired'), value: s.expired ?? 0, color: 'warning' },
    { key: 'suspended', label: t('admin.analytics.statuses.suspended'), value: s.suspended ?? 0, color: 'error' },
    { key: 'cancelled', label: t('admin.analytics.statuses.cancelled'), value: s.cancelled ?? 0, color: 'secondary' },
  ]
})

const chartData = computed(() => {
  const trend = statsData.value?.signups_trend ?? []
  return {
    labels: trend.map(row => row.label),
    datasets: [
      {
        label: t('admin.analytics.signupsTrend'),
        data: trend.map(row => row.value),
        borderColor: '#6750A4',
        backgroundColor: 'rgba(103, 80, 164, 0.15)',
        fill: true,
        tension: 0.35,
      },
    ],
  }
})

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }

async function loadStats() {
  try {
    const { data } = await getAdminAnalyticsApi({ date_from: dateFrom.value, date_to: dateTo.value })
    statsData.value = data.data
  } catch {
    statsData.value = null
  }
}

onMounted(loadStats)
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.analytics.title')" :subtitle="t('admin.analytics.subtitle')" />

    <v-row>
      <v-col v-for="stat in stats" :key="stat.title" cols="12" sm="6" md="3">
        <v-card variant="flat" border rounded="lg">
          <v-card-text class="d-flex align-center ga-4">
            <v-avatar :color="stat.color" variant="tonal" size="48">
              <v-icon :icon="stat.icon" />
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">{{ stat.title }}</div>
              <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense class="mt-2 align-center">
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateFrom" :label="t('admin.analytics.dateFrom')" :clearable="false" @update:model-value="loadStats" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateTo" :label="t('admin.analytics.dateTo')" :clearable="false" @update:model-value="loadStats" />
      </v-col>
    </v-row>

    <v-row>
      <v-col v-for="stat in periodStats" :key="stat.title" cols="12" sm="6" md="3">
        <v-card variant="flat" border rounded="lg">
          <v-card-text class="d-flex align-center ga-4">
            <v-avatar :color="stat.color" variant="tonal" size="48">
              <v-icon :icon="stat.icon" />
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">{{ stat.title }}</div>
              <div class="text-h6 font-weight-bold">{{ stat.value }}</div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mt-2">
      <v-col cols="12" md="8">
        <v-card variant="flat" border rounded="lg">
          <v-card-title>{{ t('admin.analytics.signupsTrend') }}</v-card-title>
          <v-card-text style="height: 300px">
            <Line :data="chartData" :options="chartOptions" />
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="flat" border rounded="lg">
          <v-card-title>{{ t('admin.analytics.subscriptionsByStatus') }}</v-card-title>
          <v-list density="comfortable">
            <v-list-item v-for="row in subscriptionRows" :key="row.key">
              <template #prepend>
                <v-icon icon="mdi-circle-medium" :color="row.color" />
              </template>
              <v-list-item-title>{{ row.label }}</v-list-item-title>
              <template #append>
                <span class="text-h6 font-weight-bold">{{ row.value }}</span>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
