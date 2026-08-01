<script setup>
import { ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { commissionEntrySchema } from '@/utils/validators'
import { createCommissionEntryApi } from '@/apis/commission.api'
import { getUsersApi } from '@/apis/user.api'
import { getOrdersApi } from '@/apis/order.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()
const users = ref([])
const orders = ref([])
const orderSearchLoading = ref(false)
// Selecting an order re-fires @update:search with that order's own display
// title (Vuetify syncing the input box's visible text) — treating it as a
// fresh query would wipe `orders` down to whatever matches that title text,
// so reopening the dropdown to pick a different order could show no
// options. Tracking the title just selected lets searchOrders ignore it.
const lastSelectedOrderTitle = ref(null)

watch(() => props.modelValue, async (open) => {
  if (open) {
    errorMessage.value = ''
    const { data } = await getUsersApi()
    users.value = data.data
    loadInitialOrders()
  }
})

async function loadInitialOrders() {
  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ perPage: 20 })
    orders.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

async function searchOrders(term) {
  if (!term) return loadInitialOrders()
  if (term === lastSelectedOrderTitle.value) return
  if (term.length < 2) return

  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ search: term, perPage: 20 })
    orders.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

function selectOrder(orderId, setFieldValue) {
  setFieldValue('order_id', orderId)
  const order = orders.value.find((o) => o.id === orderId)
  lastSelectedOrderTitle.value = order ? `${order.customer?.name ?? ''} — $${order.total}` : null
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await createCommissionEntryApi(values)
    appStore.notify(t('commissions.messages.createdSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'commissions.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('commissions.newEntry')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm
      :id="formId"
      :schema="commissionEntrySchema"
      :initial-values="{ user_id: null, order_id: null, amount: null, earned_date: null, notes: '' }"
      @submit="onSubmit"
    >
      <template #default="{ errors, values, setFieldValue }">
        <v-select
          :model-value="values.user_id"
          :label="`${t('fields.assignedTo')} *`"
          item-title="name"
          item-value="id"
          :items="users"
          :error-messages="errors.user_id"
          class="mb-2"
          @update:model-value="setFieldValue('user_id', $event)"
        />

        <v-autocomplete
          :model-value="values.order_id"
          :label="t('commissions.linkedOrder')"
          clearable
          item-value="id"
          :items="orders.map(o => ({ id: o.id, title: `${o.customer?.name ?? ''} — $${o.total}` }))"
          :loading="orderSearchLoading"
          no-filter
          class="mb-2"
          @update:search="searchOrders"
          @update:model-value="selectOrder($event, setFieldValue)"
        />

        <v-text-field :model-value="values.amount" :label="`${t('invoices.amount')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.amount" class="mb-2" @update:model-value="setFieldValue('amount', $event)" />

        <AppDatePicker
          :model-value="values.earned_date"
          :label="`${t('commissions.earnedDate')} *`"
          :error-messages="errors.earned_date"
          :clearable="false"
          class="mb-2"
          @update:model-value="setFieldValue('earned_date', $event)"
        />

        <v-textarea :model-value="values.notes" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" @update:model-value="setFieldValue('notes', $event)" />
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
