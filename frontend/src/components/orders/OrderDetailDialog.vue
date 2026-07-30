<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import {
  getOrderApi,
  confirmOrderApi,
  startOrderProductionApi,
  readyOrderForDeliveryApi,
  deliverOrderApi,
} from '@/apis/order.api'
import { getUsersApi } from '@/apis/user.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  orderId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed', 'cancel-requested', 'create-invoice-requested'])

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const order = ref(null)
const loading = ref(false)
const actionLoading = ref(false)
const users = ref([])
const assignedUserId = ref(null)

const STATUS_MAP = computed(() => ({
  pending: { color: 'warning', label: t('orders.status.pending') },
  confirmed: { color: 'info', label: t('orders.status.confirmed') },
  in_production: { color: 'primary', label: t('orders.status.inProduction') },
  ready_for_delivery: { color: 'secondary', label: t('orders.status.readyForDelivery') },
  delivered: { color: 'success', label: t('orders.status.delivered') },
  cancelled: { color: 'error', label: t('orders.status.cancelled') },
}))

async function load() {
  if (!props.orderId) return
  loading.value = true
  try {
    const { data } = await getOrderApi(props.orderId)
    order.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.modelValue, props.orderId], async ([open]) => {
  if (open) {
    await load()
    const { data } = await getUsersApi()
    users.value = data.data
  }
}, { immediate: true })

async function runAction(action) {
  actionLoading.value = true
  try {
    if (action === 'confirm') await confirmOrderApi(props.orderId)
    if (action === 'start-production') await startOrderProductionApi(props.orderId, assignedUserId.value)
    if (action === 'ready') await readyOrderForDeliveryApi(props.orderId)
    if (action === 'deliver') await deliverOrderApi(props.orderId)
    await load()
    emit('changed')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const canUpdate = computed(() => auth.hasPermission('orders.update'))
const canCancel = computed(() => order.value && !['delivered', 'cancelled'].includes(order.value.status))
const canCreateInvoice = computed(() => auth.hasPermission('invoices.create') && order.value && order.value.status !== 'cancelled')

const EDITING_STATUS_LABELS = computed(() => ({
  pending: t('editing.status.pending'),
  in_progress: t('editing.status.inProgress'),
  in_review: t('editing.status.inReview'),
  revision_requested: t('editing.status.revisionRequested'),
  completed: t('editing.status.completed'),
}))
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('orders.orderDetails')" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="order">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <div class="text-h6">{{ order.customer?.name }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ order.customer?.phone }}</div>
        </div>
        <AppStatusChip :status="order.status" :map="STATUS_MAP" />
      </div>

      <v-table density="compact" class="mb-4">
        <thead>
          <tr><th>{{ t('fields.name') }}</th><th>{{ t('fields.unitPrice') }}</th><th>{{ t('fields.quantity') }}</th><th>{{ t('fields.total') }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in order.items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>${{ item.unit_price }}</td>
            <td>{{ item.quantity }}</td>
            <td>${{ item.line_total }}</td>
          </tr>
        </tbody>
      </v-table>

      <div class="d-flex justify-end mb-4">
        <div style="min-width: 220px">
          <div class="d-flex justify-space-between text-body-2">
            <span>{{ t('fields.subtotal') }}</span><span>${{ order.subtotal }}</span>
          </div>
          <div class="d-flex justify-space-between text-body-2">
            <span>{{ t('fields.discount') }}</span><span>-${{ order.discount_amount }}</span>
          </div>
          <div class="d-flex justify-space-between text-h6">
            <span>{{ t('fields.total') }}</span><span>${{ order.total }}</span>
          </div>
        </div>
      </div>

      <v-alert v-if="order.status === 'cancelled'" type="error" variant="tonal" density="compact" class="mb-4">
        {{ order.cancelled_reason }}
      </v-alert>

      <div v-if="order.editing_task" class="mb-4">
        <div class="text-subtitle-2 mb-1">{{ t('orders.editingTask') }}</div>
        <div class="text-body-2">
          {{ t('fields.status') }}: <strong>{{ EDITING_STATUS_LABELS[order.editing_task.status] }}</strong>
          <span v-if="order.editing_task.assigned_user">
            {{ t('orders.assignedToSuffix', { name: order.editing_task.assigned_user.name }) }}
          </span>
        </div>
      </div>

      <div v-if="canUpdate" class="d-flex flex-wrap ga-2">
        <v-btn v-if="order.status === 'pending'" color="info" variant="flat" :loading="actionLoading" @click="runAction('confirm')">
          {{ t('common.confirm') }}
        </v-btn>

        <template v-if="order.status === 'confirmed'">
          <v-select
            v-model="assignedUserId"
            :label="t('orders.assignEditor')"
            clearable
            density="compact"
            item-title="name"
            item-value="id"
            :items="users"
            style="max-width: 220px"
            hide-details
          />
          <v-btn color="primary" variant="flat" :loading="actionLoading" @click="runAction('start-production')">
            {{ t('orders.startProduction') }}
          </v-btn>
        </template>

        <v-btn v-if="order.status === 'in_production'" color="secondary" variant="flat" :loading="actionLoading" @click="runAction('ready')">
          {{ t('orders.markReady') }}
        </v-btn>

        <v-btn v-if="order.status === 'ready_for_delivery'" color="success" variant="flat" :loading="actionLoading" @click="runAction('deliver')">
          {{ t('orders.deliver') }}
        </v-btn>

        <v-btn v-if="canCreateInvoice" color="primary" variant="tonal" prepend-icon="mdi-receipt-text-plus-outline" @click="emit('create-invoice-requested', order)">
          {{ t('orders.createInvoice') }}
        </v-btn>

        <v-btn v-if="canCancel" color="error" variant="text" @click="emit('cancel-requested', order.id)">
          {{ t('orders.cancelOrder') }}
        </v-btn>
      </div>
    </div>
  </AppDialog>
</template>
