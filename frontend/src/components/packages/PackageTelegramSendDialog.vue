<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppApiErrorAlert from '@/components/common/AppApiErrorAlert.vue'
import { sendPackageTelegramApi } from '@/apis/package.api'
import { getCustomersApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  packageId: { type: String, default: null },
  packageName: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()

const customerOptions = ref([])
const customerSearchLoading = ref(false)
const selectedCustomerId = ref(null)
// Same fix as AlbumFormDialog/BookingFormDialog/InvoiceFormDialog/
// OrderFormDialog: selecting a customer re-fires @update:search with that
// customer's own name (Vuetify syncing the input box's visible text) —
// tracking it lets searchCustomers ignore that one echoed call instead of
// treating it as a fresh query that narrows the option list down.
const lastSelectedCustomerName = ref(null)
const sending = ref(false)
const submitError = ref(null)
const format = ref('text')

watch(() => props.modelValue, (open) => {
  if (open) {
    selectedCustomerId.value = null
    lastSelectedCustomerName.value = null
    submitError.value = null
    format.value = 'text'
    loadInitialCustomers()
  }
})

async function loadInitialCustomers() {
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

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
  selectedCustomerId.value = customerId
  lastSelectedCustomerName.value = customerOptions.value.find((c) => c.id === customerId)?.name ?? null
}

const selectedCustomer = computed(() => customerOptions.value.find((c) => c.id === selectedCustomerId.value) ?? null)
const customerNotLinked = computed(() => selectedCustomer.value && !selectedCustomer.value.telegram_connected)

async function send() {
  if (!selectedCustomerId.value) return

  sending.value = true
  submitError.value = null
  try {
    await sendPackageTelegramApi(props.packageId, selectedCustomerId.value, format.value)
    appStore.notify(t('packages.telegram.sentSuccess'))
    emit('update:modelValue', false)
  } catch (error) {
    submitError.value = error
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('packages.telegram.dialogTitle', { name: packageName })"
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <AppApiErrorAlert :error="submitError" fallback-key="packages.telegram.sendError" />

    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('packages.telegram.hint') }}</p>

    <v-autocomplete
      :model-value="selectedCustomerId"
      :label="t('fields.customer')"
      item-title="name"
      item-value="id"
      :items="customerOptions"
      :loading="customerSearchLoading"
      no-filter
      @update:search="searchCustomers"
      @update:model-value="selectCustomer"
    >
      <template #item="{ props: itemProps, item }">
        <v-list-item v-bind="itemProps" :subtitle="item.raw.phone">
          <template #append>
            <v-icon v-if="item.raw.telegram_connected" icon="mdi-check-circle" color="success" size="small" />
          </template>
        </v-list-item>
      </template>
    </v-autocomplete>

    <v-alert v-if="customerNotLinked" type="warning" variant="tonal" density="compact" class="mt-2">
      {{ t('packages.telegram.notLinkedHint') }}
    </v-alert>

    <v-btn-toggle v-model="format" mandatory density="compact" color="primary" variant="outlined" class="mt-4">
      <v-btn value="text" size="small">{{ t('packages.sendAsText') }}</v-btn>
      <v-btn value="image" size="small">{{ t('packages.sendAsImage') }}</v-btn>
    </v-btn-toggle>

    <template #actions>
      <v-btn variant="text" :disabled="sending" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn
        color="primary"
        variant="flat"
        :disabled="!selectedCustomerId || customerNotLinked"
        :loading="sending"
        @click="send"
      >
        {{ t('packages.telegram.send') }}
      </v-btn>
    </template>
  </AppDialog>
</template>
