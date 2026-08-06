<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import {
  getCustomerApi,
  addCustomerNoteApi,
  deleteCustomerNoteApi,
  toggleCustomerFavoriteApi,
  getCustomerTelegramLinkApi,
  unlinkCustomerTelegramApi,
  getCustomerTelegramActivityApi,
} from '@/apis/customer.api'
import { formatDate, formatDateTime } from '@/utils/dateFormat'
import { translateApiMessage } from '@/utils/apiMessages'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  customerId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()
const appStore = useAppStore()
const customer = ref(null)
const loading = ref(false)
const noteText = ref('')
const noteLoading = ref(false)
const telegramLoading = ref(false)
const telegramLink = ref(null)
const telegramActivity = ref([])
const telegramActivityLoading = ref(false)

async function load() {
  if (!props.customerId) return
  loading.value = true
  try {
    const { data } = await getCustomerApi(props.customerId)
    customer.value = data.data
  } finally {
    loading.value = false
  }
}

async function loadTelegramActivity() {
  if (!props.customerId) return
  telegramActivityLoading.value = true
  try {
    const { data } = await getCustomerTelegramActivityApi(props.customerId, { perPage: 5 })
    telegramActivity.value = data.data
  } finally {
    telegramActivityLoading.value = false
  }
}

watch(() => [props.modelValue, props.customerId], ([open]) => {
  if (open) {
    telegramLink.value = null
    load()
    loadTelegramActivity()
  }
}, { immediate: true })

async function addNote() {
  if (!noteText.value.trim()) return
  noteLoading.value = true
  try {
    await addCustomerNoteApi(props.customerId, noteText.value)
    noteText.value = ''
    await load()
    emit('changed')
  } finally {
    noteLoading.value = false
  }
}

async function removeNote(noteId) {
  await deleteCustomerNoteApi(props.customerId, noteId)
  await load()
  emit('changed')
}

async function toggleFavorite() {
  await toggleCustomerFavoriteApi(props.customerId)
  await load()
  emit('changed')
}

async function generateTelegramLink() {
  telegramLoading.value = true
  try {
    const { data } = await getCustomerTelegramLinkApi(props.customerId)
    if (data.data.linked) {
      await load()
    } else {
      telegramLink.value = data.data.link
    }
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    telegramLoading.value = false
  }
}

async function copyTelegramLink() {
  await window.navigator.clipboard.writeText(telegramLink.value)
  appStore.notify(t('customers.telegram.linkCopied'))
}

async function unlinkTelegram() {
  telegramLoading.value = true
  try {
    await unlinkCustomerTelegramApi(props.customerId)
    telegramLink.value = null
    await load()
    emit('changed')
  } finally {
    telegramLoading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('customers.dialogs.detailsTitle')" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="customer">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <div class="text-h6">{{ customer.name }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ customer.phone }} <span v-if="customer.email">&middot; {{ customer.email }}</span></div>
        </div>
        <div class="d-flex ga-2">
          <AppStatusChip v-if="customer.is_blacklisted" status="blacklisted" :map="{ blacklisted: { color: 'error', label: t('customers.status.blacklisted') } }" />
          <v-btn
            :icon="customer.is_favorite ? 'mdi-star' : 'mdi-star-outline'"
            :color="customer.is_favorite ? 'warning' : undefined"
            variant="text"
            @click="toggleFavorite"
          />
        </div>
      </div>

      <div class="d-flex flex-wrap ga-1 mb-4">
        <v-chip v-for="tag in customer.tags" :key="tag.id" size="small" :color="tag.color" variant="tonal" label>
          {{ tag.name }}
        </v-chip>
      </div>

      <v-alert v-if="customer.is_blacklisted" type="error" variant="tonal" density="compact" class="mb-4">
        {{ customer.blacklist_reason }}
      </v-alert>

      <v-card variant="outlined" class="mb-4">
        <v-card-text>
          <div class="d-flex align-center justify-space-between flex-wrap ga-2">
            <div class="d-flex align-center ga-2">
              <v-icon icon="mdi-send-circle-outline" :color="customer.telegram_connected ? 'success' : undefined" />
              <span class="text-body-2">
                {{ customer.telegram_connected ? t('customers.telegram.connected') : t('customers.telegram.notConnected') }}
              </span>
            </div>
            <v-btn
              v-if="customer.telegram_connected"
              size="small"
              variant="text"
              color="error"
              :loading="telegramLoading"
              @click="unlinkTelegram"
            >
              {{ t('customers.telegram.unlink') }}
            </v-btn>
            <v-btn v-else size="small" variant="tonal" :loading="telegramLoading" @click="generateTelegramLink">
              {{ t('customers.telegram.generateLink') }}
            </v-btn>
          </div>

          <div v-if="telegramLink" class="mt-3">
            <p class="text-caption text-medium-emphasis mb-1">{{ t('customers.telegram.shareHint') }}</p>
            <div class="d-flex align-center ga-2">
              <v-text-field :model-value="telegramLink" readonly density="compact" hide-details />
              <v-btn icon="mdi-content-copy" size="small" variant="text" @click="copyTelegramLink" />
              <v-btn icon="mdi-open-in-new" size="small" variant="text" :href="telegramLink" target="_blank" />
            </div>
          </div>

          <v-divider class="my-3" />

          <div class="text-caption text-medium-emphasis mb-2">{{ t('customers.telegram.activityTitle') }}</div>
          <div v-if="telegramActivityLoading" class="d-flex justify-center py-2">
            <v-progress-circular indeterminate size="20" color="primary" />
          </div>
          <div v-else-if="!telegramActivity.length" class="text-body-2 text-medium-emphasis">
            {{ t('customers.telegram.activityEmpty') }}
          </div>
          <v-list v-else density="compact">
            <v-list-item v-for="entry in telegramActivity" :key="entry.id">
              <template #prepend>
                <v-icon
                  :icon="entry.status === 'sent' ? 'mdi-check-circle' : 'mdi-alert-circle'"
                  :color="entry.status === 'sent' ? 'success' : 'error'"
                  size="18"
                />
              </template>
              <v-list-item-title class="text-body-2">
                {{ t(`telegramActivity.types.${entry.type}`) }}<span v-if="entry.subject_label"> — {{ entry.subject_label }}</span>
              </v-list-item-title>
              <v-list-item-subtitle>{{ formatDateTime(entry.created_at) }} &middot; {{ entry.sent_by_name }}</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>

      <v-divider class="mb-4" />

      <div class="text-subtitle-2 mb-2">{{ t('fields.notes') }}</div>

      <div class="d-flex ga-2 mb-4">
        <v-textarea v-model="noteText" rows="2" auto-grow density="compact" :placeholder="t('customers.notes.addPlaceholder')" hide-details />
        <v-btn icon="mdi-send" color="primary" :loading="noteLoading" @click="addNote" />
      </div>

      <div v-if="!customer.notes?.length" class="text-body-2 text-medium-emphasis">{{ t('customers.notes.empty') }}</div>

      <v-list v-else density="compact">
        <v-list-item v-for="note in customer.notes" :key="note.id">
          <v-list-item-title>{{ note.note }}</v-list-item-title>
          <v-list-item-subtitle>{{ note.author }} &middot; {{ formatDate(note.created_at) }}</v-list-item-subtitle>
          <template #append>
            <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" @click="removeNote(note.id)" />
          </template>
        </v-list-item>
      </v-list>
    </div>
  </AppDialog>
</template>
