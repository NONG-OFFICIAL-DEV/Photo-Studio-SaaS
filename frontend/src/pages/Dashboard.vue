<script setup>
import { ref, computed } from 'vue'
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
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { resendVerificationApi } from '@/apis/auth.api'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const resendLoading = ref(false)

/*
 * Placeholder figures — real numbers come from the Reporting module
 * (Dashboard aggregation endpoint) once Bookings/Invoices ship. The cards
 * and chart wiring below are the real, reusable shell for that data.
 */
const stats = computed(() => [
  { title: t('dashboard.todayRevenue'), value: '$0.00', icon: 'mdi-cash', color: 'success' },
  { title: t('dashboard.monthlyRevenue'), value: '$0.00', icon: 'mdi-chart-line', color: 'primary' },
  { title: t('dashboard.newCustomers'), value: '0', icon: 'mdi-account-plus-outline', color: 'info' },
  { title: t('dashboard.bookings'), value: '0', icon: 'mdi-calendar-check-outline', color: 'secondary' },
  { title: t('dashboard.pendingEditing'), value: '0', icon: 'mdi-image-edit-outline', color: 'warning' },
  { title: t('dashboard.readyForDelivery'), value: '0', icon: 'mdi-truck-delivery-outline', color: 'tertiary' },
  { title: t('dashboard.completedOrders'), value: '0', icon: 'mdi-check-circle-outline', color: 'success' },
])

const chartData = computed(() => ({
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [
    {
      label: t('dashboard.monthlyRevenue'),
      data: [0, 0, 0, 0, 0, 0],
      borderColor: '#6750A4',
      backgroundColor: 'rgba(103, 80, 164, 0.15)',
      fill: true,
      tension: 0.35,
    },
  ],
}))

const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }

async function resendVerification() {
  resendLoading.value = true
  try {
    const { data } = await resendVerificationApi()
    appStore.notify(data.message)
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
        <span>Please verify your email address.</span>
        <v-btn size="small" variant="tonal" :loading="resendLoading" @click="resendVerification">
          {{ t('auth.resendVerification') }}
        </v-btn>
      </div>
    </v-alert>

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
          <v-card-title>Top Services</v-card-title>
          <v-card-text>
            <div class="text-body-2 text-medium-emphasis">
              No data yet — available once the Bookings module ships.
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
