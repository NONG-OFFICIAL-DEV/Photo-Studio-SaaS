<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import {
  getInvoiceApi,
  sendInvoiceApi,
  recordInvoicePaymentApi,
  deleteInvoicePaymentApi,
} from '@/apis/invoice.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  invoiceId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed', 'void-requested'])

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const invoice = ref(null)
const loading = ref(false)
const actionLoading = ref(false)

const STATUS_MAP = computed(() => ({
  draft: { color: 'default', label: t('invoices.status.draft') },
  sent: { color: 'info', label: t('invoices.status.sent') },
  partially_paid: { color: 'warning', label: t('invoices.status.partiallyPaid') },
  paid: { color: 'success', label: t('invoices.status.paid') },
  overdue: { color: 'error', label: t('invoices.status.overdue') },
  void: { color: 'default', label: t('invoices.status.void') },
}))

const METHOD_ITEMS = computed(() => [
  { title: t('invoices.methods.cash'), value: 'cash' },
  { title: t('invoices.methods.bankTransfer'), value: 'bank_transfer' },
  { title: t('invoices.methods.card'), value: 'card' },
  { title: t('invoices.methods.other'), value: 'other' },
])

const payment = ref({ amount: null, method: 'cash', paid_at: null, reference: '' })
const paymentError = ref('')

async function load() {
  if (!props.invoiceId) return
  loading.value = true
  try {
    const { data } = await getInvoiceApi(props.invoiceId)
    invoice.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.modelValue, props.invoiceId], async ([open]) => {
  if (open) {
    payment.value = { amount: null, method: 'cash', paid_at: null, reference: '' }
    paymentError.value = ''
    await load()
  }
}, { immediate: true })

async function sendNow() {
  actionLoading.value = true
  try {
    await sendInvoiceApi(props.invoiceId)
    await load()
    emit('changed')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

async function recordPayment() {
  paymentError.value = ''
  if (!payment.value.amount || Number(payment.value.amount) <= 0) {
    paymentError.value = t('invoices.errors.amountRequired')
    return
  }

  actionLoading.value = true
  try {
    await recordInvoicePaymentApi(props.invoiceId, payment.value)
    payment.value = { amount: null, method: 'cash', paid_at: null, reference: '' }
    await load()
    emit('changed')
    appStore.notify(t('invoices.messages.paymentRecorded'))
  } catch (error) {
    paymentError.value = translateApiMessage(error, 'common.actionFailed')
  } finally {
    actionLoading.value = false
  }
}

async function removePayment(paymentId) {
  actionLoading.value = true
  try {
    await deleteInvoicePaymentApi(props.invoiceId, paymentId)
    await load()
    emit('changed')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const canSend = computed(() => auth.hasPermission('invoices.send') && invoice.value?.status === 'draft')
const canVoid = computed(() => auth.hasPermission('invoices.void') && invoice.value && !['paid', 'void'].includes(invoice.value.status))
const canRecordPayment = computed(() => auth.hasPermission('payments.record') && invoice.value && !['draft', 'void', 'paid'].includes(invoice.value.status))
const canDeletePayment = computed(() => auth.hasPermission('payments.delete'))
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('invoices.invoiceDetails')" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="invoice">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <div class="text-h6">{{ invoice.invoice_number }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ invoice.customer?.name }}</div>
        </div>
        <AppStatusChip :status="invoice.status" :map="STATUS_MAP" />
      </div>

      <v-table density="compact" class="mb-4">
        <thead>
          <tr><th>{{ t('fields.name') }}</th><th>{{ t('fields.unitPrice') }}</th><th>{{ t('fields.quantity') }}</th><th>{{ t('fields.total') }}</th></tr>
        </thead>
        <tbody>
          <tr v-for="item in invoice.items" :key="item.id">
            <td>{{ item.name }}</td>
            <td>${{ item.unit_price }}</td>
            <td>{{ item.quantity }}</td>
            <td>${{ item.line_total }}</td>
          </tr>
        </tbody>
      </v-table>

      <div class="d-flex justify-end mb-4">
        <div style="min-width: 240px">
          <div class="d-flex justify-space-between text-body-2">
            <span>{{ t('fields.subtotal') }}</span><span>${{ invoice.subtotal }}</span>
          </div>
          <div class="d-flex justify-space-between text-body-2">
            <span>{{ t('fields.discount') }}</span><span>-${{ invoice.discount_amount }}</span>
          </div>
          <div class="d-flex justify-space-between text-body-2">
            <span>{{ t('invoices.taxAmount') }}</span><span>${{ invoice.tax_amount }}</span>
          </div>
          <div class="d-flex justify-space-between text-h6">
            <span>{{ t('fields.total') }}</span><span>${{ invoice.total }}</span>
          </div>
          <div class="d-flex justify-space-between text-body-2 text-success">
            <span>{{ t('invoices.amountPaid') }}</span><span>${{ invoice.amount_paid }}</span>
          </div>
          <div class="d-flex justify-space-between text-subtitle-1 font-weight-bold">
            <span>{{ t('invoices.balanceDue') }}</span><span>${{ invoice.balance_due }}</span>
          </div>
        </div>
      </div>

      <v-alert v-if="invoice.status === 'void'" type="error" variant="tonal" density="compact" class="mb-4">
        {{ invoice.voided_reason }}
      </v-alert>

      <div v-if="invoice.payments?.length" class="mb-4">
        <div class="text-subtitle-2 mb-2">{{ t('invoices.payments') }}</div>
        <v-table density="compact">
          <thead>
            <tr>
              <th>{{ t('invoices.paidAt') }}</th><th>{{ t('invoices.methodLabel') }}</th><th>{{ t('invoices.amount') }}</th><th>{{ t('invoices.reference') }}</th><th style="width: 40px" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in invoice.payments" :key="p.id">
              <td>{{ p.paid_at }}</td>
              <td>{{ t(`invoices.methods.${p.method === 'bank_transfer' ? 'bankTransfer' : p.method}`) }}</td>
              <td>${{ p.amount }}</td>
              <td>{{ p.reference || '—' }}</td>
              <td>
                <v-btn v-if="canDeletePayment" icon="mdi-close" size="small" variant="text" :loading="actionLoading" @click="removePayment(p.id)" />
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>

      <div v-if="canRecordPayment" class="mb-4">
        <div class="text-subtitle-2 mb-2">{{ t('invoices.recordPayment') }}</div>
        <v-alert v-if="paymentError" type="error" variant="tonal" density="compact" class="mb-2">{{ paymentError }}</v-alert>
        <v-row dense>
          <v-col cols="6" sm="3">
            <v-text-field v-model.number="payment.amount" :label="t('invoices.amount')" type="number" step="0.01" prefix="$" density="compact" hide-details />
          </v-col>
          <v-col cols="6" sm="3">
            <v-select v-model="payment.method" :label="t('invoices.methodLabel')" :items="METHOD_ITEMS" density="compact" hide-details />
          </v-col>
          <v-col cols="6" sm="3">
            <AppDatePicker v-model="payment.paid_at" :label="t('invoices.paidAt')" />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field v-model="payment.reference" :label="t('invoices.reference')" density="compact" hide-details />
          </v-col>
        </v-row>
        <v-btn class="mt-2" color="primary" variant="tonal" :loading="actionLoading" @click="recordPayment">
          {{ t('invoices.recordPayment') }}
        </v-btn>
      </div>

      <div class="d-flex flex-wrap ga-2">
        <v-btn v-if="canSend" color="primary" variant="flat" :loading="actionLoading" @click="sendNow">
          {{ t('invoices.send') }}
        </v-btn>
        <v-btn v-if="canVoid" color="error" variant="text" @click="emit('void-requested', invoice.id)">
          {{ t('invoices.voidInvoice') }}
        </v-btn>
      </div>
    </div>
  </AppDialog>
</template>
