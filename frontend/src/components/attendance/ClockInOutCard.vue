<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import { getTodayAttendanceApi, clockInApi, clockOutApi } from '@/apis/attendance.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()

const record = ref(null)
const loading = ref(false)
const actionLoading = ref(false)

const STATUS_MAP = computed(() => ({
  present: { color: 'success', label: t('attendance.status.present') },
  late: { color: 'warning', label: t('attendance.status.late') },
  absent: { color: 'error', label: t('attendance.status.absent') },
}))

async function load() {
  loading.value = true
  try {
    const { data } = await getTodayAttendanceApi()
    record.value = data.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function clockIn() {
  actionLoading.value = true
  try {
    const { data } = await clockInApi()
    record.value = data.data
    appStore.notify(t('attendance.messages.clockedIn'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

async function clockOut() {
  actionLoading.value = true
  try {
    const { data } = await clockOutApi()
    record.value = data.data
    appStore.notify(t('attendance.messages.clockedOut'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const hasClockedIn = computed(() => Boolean(record.value?.clock_in_at))
const hasClockedOut = computed(() => Boolean(record.value?.clock_out_at))
</script>

<template>
  <v-card variant="flat" border rounded="lg">
    <v-card-text>
      <div class="d-flex align-center justify-space-between mb-3">
        <span class="text-subtitle-2">{{ t('attendance.myAttendanceToday') }}</span>
        <AppStatusChip v-if="record" :status="record.status" :map="STATUS_MAP" size="small" />
      </div>

      <div v-if="loading" class="d-flex justify-center py-4">
        <v-progress-circular indeterminate color="primary" size="24" />
      </div>

      <template v-else>
        <div v-if="hasClockedIn" class="text-body-2 text-medium-emphasis mb-3">
          <div>{{ t('attendance.clockedInAt', { time: new Date(record.clock_in_at).toLocaleTimeString() }) }}</div>
          <div v-if="hasClockedOut">
            {{ t('attendance.clockedOutAt', { time: new Date(record.clock_out_at).toLocaleTimeString() }) }}
          </div>
        </div>

        <v-btn
          v-if="!hasClockedIn"
          color="primary"
          variant="flat"
          block
          prepend-icon="mdi-login"
          :loading="actionLoading"
          @click="clockIn"
        >
          {{ t('attendance.clockIn') }}
        </v-btn>
        <v-btn
          v-else-if="!hasClockedOut"
          color="error"
          variant="tonal"
          block
          prepend-icon="mdi-logout"
          :loading="actionLoading"
          @click="clockOut"
        >
          {{ t('attendance.clockOut') }}
        </v-btn>
        <div v-else class="text-body-2 text-success">
          {{ t('attendance.doneForToday') }}
        </div>
      </template>
    </v-card-text>
  </v-card>
</template>
