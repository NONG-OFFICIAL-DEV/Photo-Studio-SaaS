<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import AppDialog from '@/components/common/AppDialog.vue'
import { getAdminPaymentClaimsApi, confirmAdminPaymentClaimApi, rejectAdminPaymentClaimApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { formatDateTime } from '@/utils/dateFormat'
import { formatCurrency } from '@/utils/currencyFormat'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()
const tableRef = ref(null)

const headers = [
  { title: t('admin.paymentClaims.tenant'), key: 'tenant_name', sortable: false },
  { title: t('billingPage.paymentClaim.amount'), key: 'claimed_amount', sortable: false },
  { title: t('billingPage.paymentClaim.note'), key: 'note', sortable: false },
  { title: t('admin.paymentClaims.receipt'), key: 'receipt_url', sortable: false },
  { title: t('admin.paymentClaims.submittedBy'), key: 'submitted_by_name', sortable: false },
  { title: t('admin.paymentClaims.submittedAt'), key: 'created_at', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
]

async function fetchClaims(params) {
  const { data } = await getAdminPaymentClaimsApi(params)
  return { items: data.data, total: data.meta.total }
}

const confirmDialog = ref(false)
const rejectDialog = ref(false)
const actionTarget = ref(null)
const actionLoading = ref(false)
const rejectNote = ref('')

function askConfirm(claim) {
  actionTarget.value = claim
  confirmDialog.value = true
}

function askReject(claim) {
  actionTarget.value = claim
  rejectNote.value = ''
  rejectDialog.value = true
}

async function handleConfirm() {
  actionLoading.value = true
  try {
    await confirmAdminPaymentClaimApi(actionTarget.value.id)
    appStore.notify(t('admin.paymentClaims.messages.confirmed'))
    confirmDialog.value = false
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.paymentClaims.messages.confirmError'), 'error')
  } finally {
    actionLoading.value = false
  }
}

async function handleReject() {
  actionLoading.value = true
  try {
    await rejectAdminPaymentClaimApi(actionTarget.value.id, rejectNote.value)
    appStore.notify(t('admin.paymentClaims.messages.rejected'))
    rejectDialog.value = false
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.paymentClaims.messages.rejectError'), 'error')
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.paymentClaims.title')" :subtitle="t('admin.paymentClaims.subtitle')" />

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchClaims" :show-search="false" item-label="claims">
        <template #[`item.claimed_amount`]="{ item }">
          {{ item.claimed_amount !== null ? formatCurrency(item.claimed_amount) : '—' }}
        </template>

        <template #[`item.note`]="{ item }">
          <span class="text-truncate d-inline-block" style="max-width: 220px">{{ item.note || '—' }}</span>
        </template>

        <template #[`item.receipt_url`]="{ item }">
          <a v-if="item.receipt_url" :href="item.receipt_url" target="_blank" rel="noopener">
            <v-avatar size="40" rounded="lg">
              <v-img :src="item.receipt_url" cover />
            </v-avatar>
          </a>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #[`item.created_at`]="{ item }">
          {{ formatDateTime(item.created_at) }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn size="small" variant="text" color="success" prepend-icon="mdi-check-circle-outline" @click="askConfirm(item)">
            {{ t('admin.paymentClaims.actions.confirm') }}
          </v-btn>
          <v-btn size="small" variant="text" color="error" prepend-icon="mdi-close-circle-outline" @click="askReject(item)">
            {{ t('admin.paymentClaims.actions.reject') }}
          </v-btn>
        </template>
      </AppTable>
    </v-card>

    <AppConfirmDialog
      v-model="confirmDialog"
      color="success"
      :title="t('admin.paymentClaims.confirmTitle')"
      :message="t('admin.paymentClaims.confirmMessage', { name: actionTarget?.tenant_name })"
      :loading="actionLoading"
      @confirm="handleConfirm"
    />

    <AppDialog :model-value="rejectDialog" :title="t('admin.paymentClaims.rejectTitle')" max-width="480" @update:model-value="rejectDialog = $event">
      <p class="text-body-2 text-medium-emphasis mb-4">{{ t('admin.paymentClaims.rejectHint', { name: actionTarget?.tenant_name }) }}</p>
      <v-textarea v-model="rejectNote" :label="t('admin.paymentClaims.reviewNote')" rows="3" />

      <template #actions>
        <v-btn variant="text" :disabled="actionLoading" @click="rejectDialog = false">{{ t('common.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="actionLoading" @click="handleReject">{{ t('admin.paymentClaims.actions.reject') }}</v-btn>
      </template>
    </AppDialog>
  </div>
</template>
