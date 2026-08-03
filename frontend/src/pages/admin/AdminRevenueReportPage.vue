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

const RANGE_PRESETS = [
  { key: 'month', months: 1 },
  { key: 'quarter', months: 3 },
  { key: 'year', months: 12 },
]

function applyPreset(months) {
  const now = new Date()
  dateFrom.value = new Date(now.getFullYear(), now.getMonth() - (months - 1), 1).toISOString().slice(0, 10)
  dateTo.value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  loadStats()
}

const stats = computed(() => {
  const d = statsData.value
  return [
    { title: t('admin.revenueReport.revenueInRange'), value: formatCurrency(d?.revenue_collected ?? 0), icon: 'mdi-cash-check', color: 'success' },
    { title: t('admin.revenueReport.mrr'), value: formatCurrency(d?.mrr ?? 0), icon: 'mdi-cash-multiple', color: 'tertiary' },
    { title: t('admin.revenueReport.newTenants'), value: String(d?.new_tenants ?? 0), icon: 'mdi-domain-plus', color: 'info' },
  ]
})

const revenueChartData = computed(() => {
  const trend = statsData.value?.revenue_trend ?? []
  return {
    labels: trend.map(row => row.label),
    datasets: [
      {
        label: t('admin.revenueReport.revenueTrend'),
        data: trend.map(row => row.value),
        borderColor: '#2E7D32',
        backgroundColor: 'rgba(46, 125, 50, 0.15)',
        fill: true,
        tension: 0.35,
      },
    ],
  }
})

const revenueChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { callbacks: { label: (ctx) => formatCurrency(ctx.parsed.y) } },
  },
}

const topTenants = computed(() => statsData.value?.top_tenants ?? [])

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
    <AppToolbar :title="t('admin.revenueReport.title')" :subtitle="t('admin.revenueReport.subtitle')" />

    <v-row dense class="align-center">
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateFrom" :label="t('admin.analytics.dateFrom')" :clearable="false" @update:model-value="loadStats" />
      </v-col>
      <v-col cols="6" sm="3">
        <AppDatePicker v-model="dateTo" :label="t('admin.analytics.dateTo')" :clearable="false" @update:model-value="loadStats" />
      </v-col>
      <v-col cols="12" sm="6" class="d-flex ga-2">
        <v-btn
          v-for="preset in RANGE_PRESETS"
          :key="preset.key"
          size="small"
          variant="tonal"
          @click="applyPreset(preset.months)"
        >
          {{ t(`admin.revenueReport.presets.${preset.key}`) }}
        </v-btn>
      </v-col>
    </v-row>

    <v-row class="mt-2">
      <v-col v-for="stat in stats" :key="stat.title" cols="12" sm="6" md="4">
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
          <v-card-title>{{ t('admin.revenueReport.revenueTrend') }}</v-card-title>
          <v-card-text style="height: 320px">
            <Line :data="revenueChartData" :options="revenueChartOptions" />
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="flat" border rounded="lg">
          <v-card-title>{{ t('admin.revenueReport.topTenants') }}</v-card-title>
          <v-card-subtitle class="text-wrap">{{ t('admin.revenueReport.topTenantsSubtitle') }}</v-card-subtitle>
          <v-list density="comfortable">
            <v-list-item v-for="(tenant, index) in topTenants" :key="tenant.tenant_id">
              <template #prepend>
                <v-avatar size="28" color="primary" variant="tonal">
                  <span class="text-caption font-weight-bold">{{ index + 1 }}</span>
                </v-avatar>
              </template>
              <v-list-item-title>{{ tenant.tenant_name ?? '—' }}</v-list-item-title>
              <v-list-item-subtitle>{{ tenant.payments_count }} {{ t('admin.revenueReport.payments') }}</v-list-item-subtitle>
              <template #append>
                <span class="text-body-2 font-weight-bold">{{ formatCurrency(tenant.total_spent) }}</span>
              </template>
            </v-list-item>

            <v-list-item v-if="topTenants.length === 0">
              <v-list-item-title class="text-body-2 text-medium-emphasis text-center py-2">
                {{ t('admin.revenueReport.noTopTenants') }}
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
