<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import NotificationPreferencesDialog from '@/components/common/NotificationPreferencesDialog.vue'
import {
  getNotificationsApi,
  markAllNotificationsReadApi,
  markNotificationReadApi,
} from '@/apis/notification.api'
import { formatDateTime } from '@/utils/dateFormat'
import { useNotificationDisplay } from '@/composables/useNotificationDisplay'

const { t } = useI18n()
const router = useRouter()
const { icon, color, message } = useNotificationDisplay()

const tableRef = ref(null)
const unreadCount = ref(0)
const preferencesDialog = ref(false)

const headers = [
  { title: '', key: 'severity', sortable: false, width: 40 },
  { title: t('notifications.message'), key: 'message', sortable: false },
  { title: t('notifications.date'), key: 'created_at', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
]

async function fetchNotifications(params) {
  const { data } = await getNotificationsApi(params)
  unreadCount.value = data.meta.unread_count
  return { items: data.data, total: data.meta.total }
}

async function handleOpen(n) {
  if (!n.read_at) {
    await markNotificationReadApi(n.id)
    tableRef.value?.refresh()
  }
  if (n.link?.name) {
    router.push({ name: n.link.name })
  }
}

async function handleMarkAllRead() {
  await markAllNotificationsReadApi()
  tableRef.value?.refresh()
}
</script>

<template>
  <div>
    <AppToolbar :title="t('notifications.title')">
      <template #actions>
        <v-btn variant="text" prepend-icon="mdi-tune-vertical" @click="preferencesDialog = true">
          {{ t('notifications.preferences.title') }}
        </v-btn>
        <v-btn
          v-if="unreadCount > 0"
          variant="tonal"
          prepend-icon="mdi-check-all"
          @click="handleMarkAllRead"
        >
          {{ t('notifications.markAllRead') }}
        </v-btn>
      </template>
    </AppToolbar>

    <AppTable
      ref="tableRef"
      :headers="headers"
      :fetch-fn="fetchNotifications"
      :show-search="false"
      item-label="notifications"
    >
      <template #[`item.severity`]="{ item }">
        <v-icon :icon="icon(item)" :color="color(item)" size="20" />
      </template>

      <template #[`item.message`]="{ item }">
        <span :class="{ 'font-weight-bold': !item.read_at }">{{ message(item) }}</span>
      </template>

      <template #[`item.created_at`]="{ item }">
        {{ formatDateTime(item.created_at) }}
      </template>

      <template #[`item.actions`]="{ item }">
        <v-btn size="small" variant="text" prepend-icon="mdi-open-in-new" @click="handleOpen(item)">
          {{ t('notifications.open') }}
        </v-btn>
      </template>
    </AppTable>

    <NotificationPreferencesDialog v-model="preferencesDialog" />
  </div>
</template>
