<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { useAppStore } from '@/stores/app'
import {
  getNotificationPreferencesApi,
  updateNotificationPreferencesApi,
  linkTelegramNotificationsApi,
  unlinkTelegramNotificationsApi,
} from '@/apis/notification.api'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(true)
const saving = ref(false)
const linkingTelegram = ref(false)
const channels = ref({ mail: true, system: true, telegram: false })
const telegramLinked = ref(false)
const telegramAvailable = ref(false)
const telegramDeepLink = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await getNotificationPreferencesApi()
    channels.value = data.data.channels
    telegramLinked.value = data.data.telegram.linked
    telegramAvailable.value = data.data.telegram.available
    if (telegramLinked.value) telegramDeepLink.value = null
  } finally {
    loading.value = false
  }
}

watch(() => props.modelValue, (open) => {
  if (open) load()
})

async function saveChannels() {
  saving.value = true
  try {
    await updateNotificationPreferencesApi(channels.value)
    appStore.notify(t('notifications.preferences.saved'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'notifications.preferences.saveError'), 'error')
    await load()
  } finally {
    saving.value = false
  }
}

async function handleConnectTelegram() {
  linkingTelegram.value = true
  try {
    const { data } = await linkTelegramNotificationsApi()
    if (data.data.linked) {
      telegramLinked.value = true
    } else {
      telegramDeepLink.value = data.data.link
      window.open(data.data.link, '_blank')
    }
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'notifications.preferences.telegramLinkError'), 'error')
  } finally {
    linkingTelegram.value = false
  }
}

async function handleDisconnectTelegram() {
  await unlinkTelegramNotificationsApi()
  telegramLinked.value = false
  telegramDeepLink.value = null
  channels.value.telegram = false
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('notifications.preferences.title')"
    max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <p class="text-body-2 text-medium-emphasis mb-2">{{ t('notifications.preferences.subtitle') }}</p>

    <div v-if="loading" class="d-flex justify-center py-6">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-list v-else density="comfortable">
      <v-list-item>
        <v-list-item-title>{{ t('notifications.preferences.system') }}</v-list-item-title>
        <v-list-item-subtitle>{{ t('notifications.preferences.systemHint') }}</v-list-item-subtitle>
        <template #append>
          <v-switch
            v-model="channels.system"
            color="primary"
            hide-details
            :disabled="saving"
            @update:model-value="saveChannels"
          />
        </template>
      </v-list-item>

      <v-list-item>
        <v-list-item-title>{{ t('notifications.preferences.mail') }}</v-list-item-title>
        <v-list-item-subtitle>{{ t('notifications.preferences.mailHint') }}</v-list-item-subtitle>
        <template #append>
          <v-switch
            v-model="channels.mail"
            color="primary"
            hide-details
            :disabled="saving"
            @update:model-value="saveChannels"
          />
        </template>
      </v-list-item>

      <v-list-item>
        <v-list-item-title>{{ t('notifications.preferences.telegram') }}</v-list-item-title>
        <v-list-item-subtitle>
          {{ telegramLinked ? t('notifications.preferences.telegramLinked') : t('notifications.preferences.telegramHint') }}
        </v-list-item-subtitle>
        <template #append>
          <v-switch
            v-model="channels.telegram"
            color="primary"
            hide-details
            :disabled="saving || !telegramLinked"
            @update:model-value="saveChannels"
          />
        </template>
      </v-list-item>

      <v-list-item v-if="telegramAvailable">
        <template #default>
          <div class="d-flex flex-wrap ga-2 align-center">
            <v-btn
              v-if="!telegramLinked"
              size="small"
              variant="tonal"
              prepend-icon="mdi-send"
              :loading="linkingTelegram"
              @click="handleConnectTelegram"
            >
              {{ t('notifications.preferences.connectTelegram') }}
            </v-btn>
            <v-btn
              v-else
              size="small"
              variant="text"
              color="error"
              prepend-icon="mdi-link-off"
              @click="handleDisconnectTelegram"
            >
              {{ t('notifications.preferences.disconnectTelegram') }}
            </v-btn>
            <v-btn
              v-if="telegramDeepLink && !telegramLinked"
              size="small"
              variant="text"
              prepend-icon="mdi-refresh"
              @click="load"
            >
              {{ t('notifications.preferences.refreshStatus') }}
            </v-btn>
          </div>
        </template>
      </v-list-item>

      <v-list-item v-else>
        <v-list-item-subtitle class="text-wrap">
          {{ t('notifications.preferences.telegramUnavailable') }}
        </v-list-item-subtitle>
      </v-list-item>
    </v-list>
  </AppDialog>
</template>
