<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import PlanLimitAlert from '@/components/common/PlanLimitAlert.vue'
import OrderFormDialog from '@/components/orders/OrderFormDialog.vue'
import OrderDetailDialog from '@/components/orders/OrderDetailDialog.vue'
import OrderCancelDialog from '@/components/orders/OrderCancelDialog.vue'
import InvoiceFormDialog from '@/components/invoices/InvoiceFormDialog.vue'
import { getOrdersApi } from '@/apis/order.api'
import { getPlanLimitsApi } from '@/apis/plan-limit.api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const tableRef = ref(null)

const limits = ref({ monthly_order_limit: null, orders_this_month_count: 0 })

onMounted(async () => {
  const { data } = await getPlanLimitsApi()
  limits.value = data.data
})

const STATUS_MAP = computed(() => ({
  pending: { color: 'warning', label: t('orders.status.pending') },
  confirmed: { color: 'info', label: t('orders.status.confirmed') },
  in_production: { color: 'primary', label: t('orders.status.inProduction') },
  ready_for_delivery: { color: 'secondary', label: t('orders.status.readyForDelivery') },
  delivered: { color: 'success', label: t('orders.status.delivered') },
  cancelled: { color: 'error', label: t('orders.status.cancelled') },
}))

const headers = computed(() => [
  { title: t('fields.customer'), key: 'customer' },
  { title: t('orders.items'), key: 'items', sortable: false },
  { title: t('fields.total'), key: 'total' },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null })

async function fetchOrders(params) {
  const { data } = await getOrdersApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const detailDialog = ref(false)
const selectedOrderId = ref(null)
const cancelDialog = ref(false)
const cancelTargetId = ref(null)
const invoiceDialog = ref(false)
const invoicePresetOrder = ref(null)

function openDetail(order) {
  selectedOrderId.value = order.id
  detailDialog.value = true
}

function onCancelRequested(orderId) {
  detailDialog.value = false
  cancelTargetId.value = orderId
  cancelDialog.value = true
}

function onCreateInvoiceRequested(order) {
  detailDialog.value = false
  invoicePresetOrder.value = order
  invoiceDialog.value = true
}

const canCreate = computed(() => auth.hasPermission('orders.create'))
</script>

<template>
  <div>
    <AppToolbar :title="t('orders.title')" :subtitle="t('orders.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="formDialog = true">{{ t('orders.newOrder') }}</v-btn>
      </template>
    </AppToolbar>

    <PlanLimitAlert :current="limits.orders_this_month_count" :limit="limits.monthly_order_limit" :resource="t('orders.title').toLowerCase()" />

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.status"
          :label="t('fields.status')"
          clearable
          density="compact"
          :items="Object.entries(STATUS_MAP).map(([value, s]) => ({ title: s.label, value }))"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchOrders"
        :filters="filters"
        item-label="orders"
      >
        <template #[`item.customer`]="{ item }">
          <span class="cursor-pointer" @click="openDetail(item)">{{ item.customer?.name }}</span>
        </template>

        <template #[`item.items`]="{ item }">
          {{ t('orders.itemsCount', { count: item.items?.length ?? 0 }) }}
        </template>

        <template #[`item.total`]="{ item }">
          ${{ item.total }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="openDetail(item)" />
        </template>
      </AppTable>
    </v-card>

    <OrderFormDialog v-model="formDialog" @saved="tableRef?.refresh()" />

    <OrderDetailDialog
      v-model="detailDialog"
      :order-id="selectedOrderId"
      @changed="tableRef?.refresh()"
      @cancel-requested="onCancelRequested"
      @create-invoice-requested="onCreateInvoiceRequested"
    />

    <OrderCancelDialog v-model="cancelDialog" :order-id="cancelTargetId" @saved="tableRef?.refresh()" />

    <InvoiceFormDialog v-model="invoiceDialog" :preset-order="invoicePresetOrder" />
  </div>
</template>
