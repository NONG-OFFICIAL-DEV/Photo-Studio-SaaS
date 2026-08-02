<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import { getTelegramActivityApi } from '@/apis/telegram.api'
import { getCustomersApi } from '@/apis/customer.api'
import { formatDateTime } from '@/utils/dateFormat'

const { t } = useI18n()
const tableRef = ref(null)

const STATUS_MAP = computed(() => ({
  sent: { color: 'success', label: t('telegramActivity.statuses.sent') },
  failed: { color: 'error', label: t('telegramActivity.statuses.failed') },
}))

const TYPE_ITEMS = computed(() => [
  { title: t('telegramActivity.types.invoice'), value: 'invoice' },
  { title: t('telegramActivity.types.album'), value: 'album' },
  { title: t('telegramActivity.types.package'), value: 'package' },
])

const STATUS_ITEMS = computed(() => [
  { title: t('telegramActivity.statuses.sent'), value: 'sent' },
  { title: t('telegramActivity.statuses.failed'), value: 'failed' },
])

const headers = computed(() => [
  { title: t('telegramActivity.columns.customer'), key: 'customer_name' },
  { title: t('telegramActivity.columns.type'), key: 'type', sortable: false },
  { title: t('telegramActivity.columns.subject'), key: 'subject_label', sortable: false },
  { title: t('telegramActivity.columns.status'), key: 'status', sortable: false },
  { title: t('telegramActivity.columns.sentBy'), key: 'sent_by_name', sortable: false },
  { title: t('telegramActivity.columns.sentAt'), key: 'created_at', sortable: false },
])

const filters = ref({ customer_id: null, type: null, status: null })
const customerOptions = ref([])
const customerSearchLoading = ref(false)
const lastSelectedCustomerName = ref(null)

async function loadInitialCustomers() {
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}
loadInitialCustomers()

async function searchCustomers(term) {
  if (!term) return loadInitialCustomers()
  if (term === lastSelectedCustomerName.value) return
  if (term.length < 2) return

  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ search: term, perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

function selectCustomer(customerId) {
  filters.value.customer_id = customerId
  lastSelectedCustomerName.value = customerOptions.value.find((c) => c.id === customerId)?.name ?? null
}

async function fetchActivity(params) {
  const { data } = await getTelegramActivityApi(params)
  return { items: data.data, total: data.meta.total }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('telegramActivity.title')" :subtitle="t('telegramActivity.subtitle')" />

    <v-row class="mb-2" dense>
      <v-col cols="12" sm="4">
        <v-autocomplete
          :model-value="filters.customer_id"
          :label="t('telegramActivity.filters.customer')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="customerOptions"
          :loading="customerSearchLoading"
          no-filter
          @update:search="searchCustomers"
          @update:model-value="selectCustomer"
          @click:clear="filters.customer_id = null"
        />
      </v-col>
      <v-col cols="6" sm="4">
        <v-select v-model="filters.type" :label="t('telegramActivity.filters.type')" clearable density="compact" :items="TYPE_ITEMS" />
      </v-col>
      <v-col cols="6" sm="4">
        <v-select v-model="filters.status" :label="t('telegramActivity.filters.status')" clearable density="compact" :items="STATUS_ITEMS" />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchActivity" :filters="filters" item-label="Telegram sends">
        <template #[`item.type`]="{ item }">
          {{ t(`telegramActivity.types.${item.type}`) }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
          <v-tooltip v-if="item.error_message" :text="item.error_message">
            <template #activator="{ props: tooltipProps }">
              <v-icon v-bind="tooltipProps" icon="mdi-information-outline" size="16" class="ml-1" />
            </template>
          </v-tooltip>
        </template>

        <template #[`item.created_at`]="{ item }">
          {{ formatDateTime(item.created_at) }}
        </template>
      </AppTable>
    </v-card>
  </div>
</template>
