<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import InvoiceFormDialog from '@/components/invoices/InvoiceFormDialog.vue'
import InvoiceDetailDialog from '@/components/invoices/InvoiceDetailDialog.vue'
import InvoiceVoidDialog from '@/components/invoices/InvoiceVoidDialog.vue'
import { getInvoicesApi } from '@/apis/invoice.api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const tableRef = ref(null)

const STATUS_MAP = computed(() => ({
  draft: { color: 'default', label: t('invoices.status.draft') },
  sent: { color: 'info', label: t('invoices.status.sent') },
  partially_paid: { color: 'warning', label: t('invoices.status.partiallyPaid') },
  paid: { color: 'success', label: t('invoices.status.paid') },
  overdue: { color: 'error', label: t('invoices.status.overdue') },
  void: { color: 'default', label: t('invoices.status.void') },
}))

const headers = computed(() => [
  { title: t('invoices.invoiceNumber'), key: 'invoice_number' },
  { title: t('fields.customer'), key: 'customer' },
  { title: t('fields.total'), key: 'total' },
  { title: t('invoices.balanceDue'), key: 'balance_due', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null })

async function fetchInvoices(params) {
  const { data } = await getInvoicesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const detailDialog = ref(false)
const selectedInvoiceId = ref(null)
const voidDialog = ref(false)
const voidTargetId = ref(null)

function openDetail(invoice) {
  selectedInvoiceId.value = invoice.id
  detailDialog.value = true
}

function onVoidRequested(invoiceId) {
  detailDialog.value = false
  voidTargetId.value = invoiceId
  voidDialog.value = true
}

const canCreate = computed(() => auth.hasPermission('invoices.create'))
</script>

<template>
  <div>
    <AppToolbar :title="t('invoices.title')" :subtitle="t('invoices.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="formDialog = true">{{ t('invoices.newInvoice') }}</v-btn>
      </template>
    </AppToolbar>

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
        :fetch-fn="fetchInvoices"
        :filters="filters"
        item-label="invoices"
      >
        <template #[`item.invoice_number`]="{ item }">
          <span class="cursor-pointer" @click="openDetail(item)">{{ item.invoice_number }}</span>
        </template>

        <template #[`item.customer`]="{ item }">
          {{ item.customer?.name }}
        </template>

        <template #[`item.total`]="{ item }">
          ${{ item.total }}
        </template>

        <template #[`item.balance_due`]="{ item }">
          ${{ item.balance_due }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" :map="STATUS_MAP" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" variant="text" color="info" @click="openDetail(item)" />
        </template>
      </AppTable>
    </v-card>

    <InvoiceFormDialog v-model="formDialog" @saved="tableRef?.refresh()" />

    <InvoiceDetailDialog
      v-model="detailDialog"
      :invoice-id="selectedInvoiceId"
      @changed="tableRef?.refresh()"
      @void-requested="onVoidRequested"
    />

    <InvoiceVoidDialog v-model="voidDialog" :invoice-id="voidTargetId" @saved="tableRef?.refresh()" />
  </div>
</template>
