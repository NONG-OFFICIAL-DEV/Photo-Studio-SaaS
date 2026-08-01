<script setup>
import { ref, computed, onMounted } from 'vue'
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
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import ClockInOutCard from '@/components/attendance/ClockInOutCard.vue'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { resendVerificationApi } from '@/apis/auth.api'
import { getDashboardStatsApi } from '@/apis/dashboard.api'
import { formatCurrency } from '@/utils/currencyFormat'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const resendLoading = ref(false)
const statsData = ref(null)

const stats = computed(() => {
  const d = statsData.value
  return [
    { title: t('dashboard.todayRevenue'), value: formatCurrency(d?.today_revenue ?? 0), icon: 'mdi-cash', color: 'success' },
    { title: t('dashboard.monthlyRevenue'), value: formatCurrency(d?.monthly_revenue ?? 0), icon: 'mdi-chart-line', color: 'primary' },
    { title: t('dashboard.newCustomers'), value: String(d?.new_customers ?? 0), icon: 'mdi-account-plus-outline', color: 'info' },
    { title: t('dashboard.bookings'), value: String(d?.bookings ?? 0), icon: 'mdi-calendar-check-outline', color: 'secondary' },
    { title: t('dashboard.pendingEditing'), value: String(d?.pending_editing ?? 0), icon: 'mdi-image-edit-outline', color: 'warning' },
    { title: t('dashboard.readyForDelivery'), value: String(d?.ready_for_delivery ?? 0), icon: 'mdi-truck-delivery-outline', color: 'tertiary' },
    { title: t('dashboard.completedOrders'), value: String(d?.completed_orders ?? 0), icon: 'mdi-check-circle-outline', color: 'success' },
  ]
})

const chartData = computed(() => {
  const trend = statsData.value?.revenue_trend ?? []
  return {
    labels: trend.map(row => row.label),
    datasets: [
      {
        label: t('dashboard.monthlyRevenue'),
        data: trend.map(row => row.value),
        borderColor: '#6750A4',
        backgroundColor: 'rgba(103, 80, 164, 0.15)',
        fill: true,
        tension: 0.35,
      },
    ],
  }
})

const topServices = computed(() => statsData.value?.top_services ?? [])

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }

async function loadStats() {
  try {
    const { data } = await getDashboardStatsApi()
    statsData.value = data.data
  } catch {
    statsData.value = null
  }
}

onMounted(loadStats)

async function resendVerification() {
  resendLoading.value = true
  try {
    const { data } = await resendVerificationApi()
    appStore.notify(t(`apiErrors.${data.code}`))
  } finally {
    resendLoading.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('menu.dashboard')" :subtitle="`${t('common.welcome')}, ${auth.user?.name}`">
      <template #actions>
        <AppStatusChip v-if="auth.tenant?.subscription" :status="auth.tenant.subscription.status" />
      </template>
    </AppToolbar>

    <v-alert
      v-if="auth.user && !auth.user.email_verified_at"
      type="warning"
      variant="tonal"
      class="mb-4"
    >
      <div class="d-flex align-center justify-space-between flex-wrap ga-2">
        <span>{{ t('auth.verifyEmailPrompt') }}</span>
        <v-btn size="small" variant="tonal" :loading="resendLoading" @click="resendVerification">
          {{ t('auth.resendVerification') }}
        </v-btn>
      </div>
    </v-alert>

    <v-row v-if="auth.hasPermission('attendance.clock')" class="mb-2">
      <v-col cols="12" sm="6" md="4" lg="3">
        <ClockInOutCard />
      </v-col>
    </v-row>

    <v-row>
      <v-col v-for="stat in stats" :key="stat.title" cols="12" sm="6" md="4" lg="3">
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
          <v-card-title>{{ t('dashboard.monthlyRevenue') }}</v-card-title>
          <v-card-text style="height: 300px">
            <Line :data="chartData" :options="chartOptions" />
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="flat" border rounded="lg" height="100%">
          <v-card-title>{{ t('dashboard.topServices') }}</v-card-title>
          <v-card-text>
            <div v-if="!topServices.length" class="text-body-2 text-medium-emphasis">
              {{ t('dashboard.noDataYet') }}
            </div>
            <v-list v-else density="compact">
              <v-list-item v-for="service in topServices" :key="service.name" :title="service.name">
                <template #append>
                  <span class="text-body-2">{{ formatCurrency(service.revenue) }}</span>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
