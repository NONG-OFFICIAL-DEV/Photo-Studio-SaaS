<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import {
  getNotificationsApi,
  getUnreadNotificationCountApi,
  markAllNotificationsReadApi,
  markNotificationReadApi,
} from '@/apis/notification.api'
import { formatDateTime } from '@/utils/dateFormat'
import { useNotificationDisplay } from '@/composables/useNotificationDisplay'
import { useAuthStore } from '@/stores/auth'
import { getEcho } from '@/plugins/echo'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const { icon, color, message } = useNotificationDisplay()

const menuOpen = ref(false)
const loading = ref(false)
const notifications = ref([])
const unreadCount = ref(0)

async function fetchUnreadCount() {
  const { data } = await getUnreadNotificationCountApi()
  unreadCount.value = data.data.count
}

async function fetchRecent() {
  loading.value = true
  try {
    const { data } = await getNotificationsApi({ perPage: 10 })
    notifications.value = data.data
    unreadCount.value = data.meta.unread_count
  } finally {
    loading.value = false
  }
}

async function handleItemClick(n) {
  menuOpen.value = false

  if (!n.read_at) {
    markNotificationReadApi(n.id).catch(() => {})
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }

  if (n.link?.name) {
    router.push({ name: n.link.name })
  }
}

async function handleMarkAllRead() {
  await markAllNotificationsReadApi()
  notifications.value = notifications.value.map(n => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
  unreadCount.value = 0
}

function handleViewAll() {
  menuOpen.value = false
  router.push({ name: auth.isSuperAdmin ? 'admin-notifications' : 'notifications' })
}

/*
 * Pushed the instant a new row lands on the backend (see
 * AppServiceProvider::configureNotificationBroadcast() + App\Events\
 * NotificationCreated) — only prepend to the visible list if the dropdown
 * is already open and has loaded something, otherwise just bump the badge
 * and let the next menu-open do a normal fetchRecent().
 */
function handleLiveNotification(payload) {
  unreadCount.value += 1
  if (menuOpen.value) {
    notifications.value = [payload, ...notifications.value]
  }
}

let pollHandle = null
let channelName = null

onMounted(() => {
  fetchUnreadCount()
  // Real-time push (see handleLiveNotification) makes this a fallback for
  // a dropped/never-established socket, not the primary delivery path —
  // hence the long interval instead of the old 60s poll.
  pollHandle = window.setInterval(fetchUnreadCount, 5 * 60 * 1000)

  const echo = getEcho()
  if (echo && auth.user?.id) {
    channelName = `App.Models.User.${auth.user.id}`
    echo.private(channelName).listen('.notification.created', handleLiveNotification)
  }
})

onUnmounted(() => {
  window.clearInterval(pollHandle)
  if (channelName) {
    getEcho()?.leave(channelName)
  }
})
</script>

<template>
  <v-menu
    v-model="menuOpen"
    location="bottom end"
    transition="scale-transition"
    :close-on-content-click="false"
    min-width="340"
    max-width="380"
    @update:model-value="(val) => val && fetchRecent()"
  >
    <template #activator="{ props: menuProps }">
      <v-btn v-bind="menuProps" icon variant="text">
        <v-badge :content="unreadCount" :model-value="unreadCount > 0" color="error" floating>
          <v-icon icon="mdi-bell-outline" size="22" />
        </v-badge>
      </v-btn>
    </template>

    <v-card elevation="0" rounded="lg" class="overflow-hidden border">
      <div class="d-flex align-center justify-space-between pa-3 border-b">
        <span class="text-subtitle-2 font-weight-bold">{{ t('notifications.title') }}</span>
        <v-btn
          v-if="unreadCount > 0"
          variant="text"
          size="small"
          density="compact"
          class="text-none"
          @click="handleMarkAllRead"
        >
          {{ t('notifications.markAllRead') }}
        </v-btn>
      </div>

      <v-progress-linear v-if="loading" indeterminate color="primary" height="2" />

      <v-list density="comfortable" class="py-0" style="max-height: 360px; overflow-y: auto">
        <v-list-item
          v-for="n in notifications"
          :key="n.id"
          rounded="md"
          class="mx-1 my-1"
          :class="{ 'bg-primary-lighten-5': !n.read_at }"
          @click="handleItemClick(n)"
        >
          <template #prepend>
            <v-icon :icon="icon(n)" :color="color(n)" size="20" class="mr-2" />
          </template>
          <v-list-item-title class="text-body-2 text-wrap" :class="{ 'font-weight-bold': !n.read_at }">
            {{ message(n) }}
          </v-list-item-title>
          <v-list-item-subtitle class="text-caption">
            {{ formatDateTime(n.created_at) }}
          </v-list-item-subtitle>
        </v-list-item>

        <v-list-item v-if="!loading && notifications.length === 0">
          <v-list-item-title class="text-body-2 text-medium-emphasis text-center py-2">
            {{ t('notifications.empty') }}
          </v-list-item-title>
        </v-list-item>
      </v-list>

      <v-divider />
      <v-list-item rounded="md" class="ma-1 text-center" density="compact" @click="handleViewAll">
        <v-list-item-title class="text-body-2 text-primary font-weight-medium">
          {{ t('notifications.viewAll') }}
        </v-list-item-title>
      </v-list-item>
    </v-card>
  </v-menu>
</template>
