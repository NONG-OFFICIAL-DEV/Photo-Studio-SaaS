<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import RenewDialog from '@/components/billing/RenewDialog.vue'
import ChangePlanDialog from '@/components/billing/ChangePlanDialog.vue'
import PaymentClaimDialog from '@/components/billing/PaymentClaimDialog.vue'
import { getBillingApi, getBillingPaymentsApi, cancelBillingApi, resumeBillingApi } from '@/apis/billing.api'
import { useAppStore } from '@/stores/app'
import { formatDate, formatDateTime } from '@/utils/dateFormat'
import { formatCurrency } from '@/utils/currencyFormat'

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(true)
const subscription = ref(null)
const usage = ref(null)
const payments = ref([])
const paymentInfo = ref(null)
const pendingPaymentClaim = ref(null)

const renewDialog = ref(false)
const changePlanDialog = ref(false)
const paymentClaimDialog = ref(false)
const confirmCancel = ref(false)
const confirmResume = ref(false)
const actionLoading = ref(false)

// Set when "Renew" redirected here because the current plan is free (see
// RenewDialog's @choose-plan) — picking a plan in that case should flow
// straight into paying for it, not just switch plan_id and stop. Cleared
// up front by BOTH open paths (see openChangePlan below), not by watching
// the dialog close — ChangePlanDialog emits 'changed' then
// 'update:modelValue' back-to-back on success, so a close-watcher would
// clear the flag before onPlanChanged's `await load()` continuation ever
// gets to read it.
const resumeRenewAfterPlanChange = ref(false)

function openChangePlan(fromRenew = false) {
  resumeRenewAfterPlanChange.value = fromRenew
  changePlanDialog.value = true
}

async function onPlanChanged() {
  await load()

  if (resumeRenewAfterPlanChange.value) {
    resumeRenewAfterPlanChange.value = false
    renewDialog.value = true
  }
}

async function load() {
  loading.value = true
  try {
    const [{ data: billing }, { data: paymentsData }] = await Promise.all([getBillingApi(), getBillingPaymentsApi()])
    subscription.value = billing.data.subscription
    usage.value = billing.data.usage
    paymentInfo.value = billing.data.payment_info
    pendingPaymentClaim.value = billing.data.pending_payment_claim
    payments.value = paymentsData.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

const isCancelled = computed(() => Boolean(subscription.value?.cancelled_at))
const hasPaymentInfo = computed(() =>
  Boolean(paymentInfo.value?.khqr_image_url || paymentInfo.value?.bank_name))
const endsAtLabel = computed(() => {
  const date =
    subscription.value?.status === 'trial'
      ? subscription.value?.trial_ends_at
      : subscription.value?.current_period_ends_at
  return date ? formatDate(date) : '—'
})

function usagePercent(count, limit) {
  if (!limit) return 0
  return Math.min(100, Math.round((count / limit) * 100))
}

async function confirmCancelSubscription() {
  actionLoading.value = true
  try {
    await cancelBillingApi()
    appStore.notify(t('billingPage.messages.cancelled'))
    confirmCancel.value = false
    await load()
  } finally {
    actionLoading.value = false
  }
}

async function confirmResumeSubscription() {
  actionLoading.value = true
  try {
    await resumeBillingApi()
    appStore.notify(t('billingPage.messages.resumed'))
    confirmResume.value = false
    await load()
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('billingPage.title')" :subtitle="t('billingPage.subtitle')" />

    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else-if="subscription">
      <v-alert v-if="isCancelled" type="warning" variant="tonal" class="mb-4">
        {{ t('billingPage.cancelledNotice', { date: endsAtLabel }) }}
      </v-alert>

      <v-row>
        <v-col cols="12" md="7">
          <v-card variant="flat" border rounded="lg" class="h-100">
            <v-card-title class="d-flex align-center justify-space-between">
              {{ subscription.plan?.name }}
              <AppStatusChip :status="subscription.status" />
            </v-card-title>
            <v-card-text>
              <div v-if="subscription.amount" class="text-h5 font-weight-bold mb-2">
                {{ formatCurrency(subscription.amount) }}
                <span class="text-caption text-medium-emphasis"
                  >/ {{ t(`billingPage.cycles.${subscription.billing_cycle}`) }}</span
                >
              </div>
              <p class="text-body-2 text-medium-emphasis mb-1">
                {{
                  subscription.status === 'trial'
                    ? t('billingPage.trialEnds', { date: endsAtLabel })
                    : t('billingPage.periodEnds', { date: endsAtLabel })
                }}
              </p>

              <div class="d-flex flex-wrap ga-2 mt-4">
                <v-btn color="primary" prepend-icon="mdi-cash-check" @click="renewDialog = true">{{
                  t('billingPage.actions.renew')
                }}</v-btn>
                <v-btn variant="outlined" prepend-icon="mdi-swap-horizontal" @click="openChangePlan()">{{
                  t('billingPage.actions.changePlan')
                }}</v-btn>
                <v-btn
                  v-if="!isCancelled"
                  variant="outlined"
                  color="error"
                  prepend-icon="mdi-close-circle-outline"
                  @click="confirmCancel = true"
                >
                  {{ t('billingPage.actions.cancel') }}
                </v-btn>
                <v-btn v-else variant="text" color="success" prepend-icon="mdi-refresh" @click="confirmResume = true">
                  {{ t('billingPage.actions.resume') }}
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card variant="flat" border rounded="lg" class="h-100">
            <v-card-title>{{ t('billingPage.usage') }}</v-card-title>
            <v-card-text>
              <div class="mb-4">
                <div class="d-flex justify-space-between text-body-2 mb-1">
                  <span>{{ t('billingPage.limits.maxUsers', { count: subscription.plan?.max_users ?? '∞' }) }}</span>
                  <span>{{ usage.users_count }} / {{ subscription.plan?.max_users ?? '∞' }}</span>
                </div>
                <v-progress-linear
                  :model-value="usagePercent(usage.users_count, subscription.plan?.max_users)"
                  height="8"
                  rounded
                  color="primary"
                />
              </div>
              <div>
                <div class="d-flex justify-space-between text-body-2 mb-1">
                  <span>{{
                    t('billingPage.limits.orders', { count: subscription.plan?.monthly_order_limit ?? '∞' })
                  }}</span>
                  <span>{{ usage.orders_this_month_count }} / {{ subscription.plan?.monthly_order_limit ?? '∞' }}</span>
                </div>
                <v-progress-linear
                  :model-value="usagePercent(usage.orders_this_month_count, subscription.plan?.monthly_order_limit)"
                  height="8"
                  rounded
                  color="primary"
                />
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-card v-if="hasPaymentInfo" variant="flat" border rounded="lg" class="mt-4">
        <v-card-title>{{ t('billingPage.howToPay.title') }}</v-card-title>
        <v-card-subtitle class="text-wrap">{{ t('billingPage.howToPay.subtitle') }}</v-card-subtitle>
        <v-card-text>
          <v-row>
            <v-col v-if="paymentInfo.khqr_image_url" cols="12" sm="4" class="text-center">
              <v-img :src="paymentInfo.khqr_image_url" max-width="220" class="mx-auto border rounded-lg" />
            </v-col>
            <v-col cols="12" sm="8">
              <v-list density="compact" class="mb-2">
                <v-list-item v-if="paymentInfo.bank_name">
                  <v-list-item-title class="text-caption text-medium-emphasis">{{ t('billingPage.howToPay.bankName') }}</v-list-item-title>
                  <v-list-item-subtitle class="text-body-1 font-weight-medium">{{ paymentInfo.bank_name }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item v-if="paymentInfo.bank_account_name">
                  <v-list-item-title class="text-caption text-medium-emphasis">{{ t('billingPage.howToPay.accountName') }}</v-list-item-title>
                  <v-list-item-subtitle class="text-body-1 font-weight-medium">{{ paymentInfo.bank_account_name }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item v-if="paymentInfo.bank_account_number">
                  <v-list-item-title class="text-caption text-medium-emphasis">{{ t('billingPage.howToPay.accountNumber') }}</v-list-item-title>
                  <v-list-item-subtitle class="text-body-1 font-weight-medium">{{ paymentInfo.bank_account_number }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
              <p v-if="paymentInfo.payment_instructions" class="text-body-2 text-medium-emphasis text-wrap mb-4">
                {{ paymentInfo.payment_instructions }}
              </p>

              <v-alert v-if="pendingPaymentClaim" type="info" variant="tonal" density="comfortable">
                {{ t('billingPage.paymentClaim.pendingNotice', { date: formatDateTime(pendingPaymentClaim.created_at) }) }}
              </v-alert>
              <v-btn v-else color="primary" variant="flat" prepend-icon="mdi-check-decagram-outline" @click="paymentClaimDialog = true">
                {{ t('billingPage.paymentClaim.iVePaid') }}
              </v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-card variant="flat" border rounded="lg" class="mt-4">
        <v-card-title>{{ t('billingPage.paymentHistory') }}</v-card-title>
        <v-table density="comfortable">
          <thead>
            <tr>
              <th>{{ t('billingPage.paidAt') }}</th>
              <th>{{ t('fields.plan') }}</th>
              <th>{{ t('billingPage.cycle') }}</th>
              <th>{{ t('fields.total') }}</th>
              <th>{{ t('billingPage.recordedBy') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in payments" :key="payment.id">
              <td>{{ formatDate(payment.paid_at) }}</td>
              <td>{{ payment.plan_name }}</td>
              <td>{{ t(`billingPage.cycles.${payment.billing_cycle}`) }}</td>
              <td>{{ formatCurrency(payment.amount) }}</td>
              <td>{{ payment.recorded_by?.name ?? t('billingPage.self') }}</td>
            </tr>
            <tr v-if="!payments.length">
              <td colspan="5" class="text-center text-medium-emphasis py-4">{{ t('common.noData') }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </template>

    <RenewDialog
      v-model="renewDialog"
      :plan="subscription?.plan"
      :current-billing-cycle="subscription?.billing_cycle"
      @renewed="load"
      @choose-plan="openChangePlan(true)"
    />
    <ChangePlanDialog
      v-model="changePlanDialog"
      :current-plan-id="subscription?.plan?.id"
      :current-billing-cycle="subscription?.billing_cycle"
      :pending-payment="resumeRenewAfterPlanChange"
      @changed="onPlanChanged"
    />
    <PaymentClaimDialog
      v-model="paymentClaimDialog"
      :default-amount="subscription?.amount"
      @submitted="load"
    />

    <AppConfirmDialog
      v-model="confirmCancel"
      :title="t('billingPage.confirmCancelTitle')"
      :message="t('billingPage.confirmCancelMessage', { date: endsAtLabel })"
      :loading="actionLoading"
      @confirm="confirmCancelSubscription"
    />
    <AppConfirmDialog
      v-model="confirmResume"
      :title="t('billingPage.confirmResumeTitle')"
      :message="t('billingPage.confirmResumeMessage')"
      color="success"
      :loading="actionLoading"
      @confirm="confirmResumeSubscription"
    />
  </div>
</template>
