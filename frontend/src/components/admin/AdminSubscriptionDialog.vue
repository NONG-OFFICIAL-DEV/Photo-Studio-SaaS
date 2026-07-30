<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import {
  getAdminPlansApi,
  changeAdminSubscriptionPlanApi,
  renewAdminSubscriptionApi,
  cancelAdminSubscriptionApi,
  resumeAdminSubscriptionApi,
  suspendAdminSubscriptionApi,
  reactivateAdminSubscriptionApi,
  getAdminSubscriptionPaymentsApi,
} from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tenant: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const actionLoading = ref(false)
const subscription = ref(null)
const payments = ref([])
const plans = ref([])
const selectedPlanId = ref(null)
const selectedCycle = ref('monthly')

watch(() => props.modelValue, async (open) => {
  if (!open || !props.tenant) return

  subscription.value = props.tenant.subscription
  selectedPlanId.value = subscription.value?.plan?.id ?? null
  selectedCycle.value = subscription.value?.billing_cycle ?? 'monthly'

  loading.value = true
  try {
    const [{ data: plansData }, { data: paymentsData }] = await Promise.all([
      getAdminPlansApi({ perPage: 100 }),
      getAdminSubscriptionPaymentsApi(props.tenant.id),
    ])
    // Free Trial (or any plan with no real price on any cycle) is a
    // one-time onboarding plan — the backend rejects switching a
    // subscription back onto it regardless of who asks, so don't offer it
    // as a destination here either.
    const payablePlans = plansData.data.filter(p => p.is_active && (p.price_monthly > 0 || p.price_quarterly > 0 || p.price_yearly > 0))

    // But the tenant's CURRENT plan (e.g. Free Trial, on any freshly
    // registered tenant) may itself be one of the excluded ones — if it's
    // missing from the list, v-select has no item to resolve a title from
    // for the pre-selected value and falls back to showing the raw plan
    // id. Always include it so the select always displays a real name.
    const currentPlan = subscription.value?.plan
    plans.value = currentPlan && !payablePlans.some(p => p.id === currentPlan.id)
      ? [currentPlan, ...payablePlans]
      : payablePlans
    payments.value = paymentsData.data
  } finally {
    loading.value = false
  }
})

const isCancelled = computed(() => Boolean(subscription.value?.cancelled_at))
const isSuspended = computed(() => subscription.value?.status === 'suspended')

async function runAction(fn, successMessage) {
  actionLoading.value = true
  try {
    const { data } = await fn()
    subscription.value = data.data
    appStore.notify(successMessage)
    emit('changed')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const changePlan = () => runAction(
  () => changeAdminSubscriptionPlanApi(props.tenant.id, selectedPlanId.value),
  t('admin.tenants.subscriptionDialog.messages.planChanged'),
)
const renew = () => runAction(
  () => renewAdminSubscriptionApi(props.tenant.id, selectedCycle.value),
  t('admin.tenants.subscriptionDialog.messages.renewed'),
)
const cancel = () => runAction(
  () => cancelAdminSubscriptionApi(props.tenant.id),
  t('admin.tenants.subscriptionDialog.messages.cancelled'),
)
const resume = () => runAction(
  () => resumeAdminSubscriptionApi(props.tenant.id),
  t('admin.tenants.subscriptionDialog.messages.resumed'),
)
const suspend = () => runAction(
  () => suspendAdminSubscriptionApi(props.tenant.id),
  t('admin.tenants.subscriptionDialog.messages.suspended'),
)
const reactivate = () => runAction(
  () => reactivateAdminSubscriptionApi(props.tenant.id),
  t('admin.tenants.subscriptionDialog.messages.reactivated'),
)
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('admin.tenants.subscriptionDialog.title', { name: tenant?.name })"
    max-width="640"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <template v-else-if="subscription">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <div class="text-h6">{{ subscription.plan?.name }}</div>
          <div class="text-body-2 text-medium-emphasis">
            {{ subscription.amount ? `$${Number(subscription.amount).toFixed(2)} / ${subscription.billing_cycle}` : '—' }}
          </div>
        </div>
        <AppStatusChip :status="subscription.status" />
      </div>

      <v-alert v-if="isCancelled" type="warning" variant="tonal" density="compact" class="mb-4">
        {{ t('admin.tenants.subscriptionDialog.cancelledNotice') }}
      </v-alert>

      <v-divider class="mb-4" />

      <div class="mb-4">
        <div class="text-subtitle-2 mb-2">{{ t('admin.tenants.subscriptionDialog.changePlan') }}</div>
        <div class="d-flex ga-2">
          <v-select
            v-model="selectedPlanId"
            :items="plans"
            item-title="name"
            item-value="id"
            density="compact"
            hide-details
            class="flex-grow-1"
          />
          <v-btn :loading="actionLoading" :disabled="selectedPlanId === subscription.plan?.id" @click="changePlan">
            {{ t('common.save') }}
          </v-btn>
        </div>
      </div>

      <div class="mb-4">
        <div class="text-subtitle-2 mb-2">{{ t('admin.tenants.subscriptionDialog.recordPayment') }}</div>
        <div class="d-flex ga-2">
          <v-select
            v-model="selectedCycle"
            :items="[
              { title: t('billingPage.cycles.monthly'), value: 'monthly' },
              { title: t('billingPage.cycles.quarterly'), value: 'quarterly' },
              { title: t('billingPage.cycles.yearly'), value: 'yearly' },
            ]"
            density="compact"
            hide-details
            class="flex-grow-1"
          />
          <v-btn color="primary" :loading="actionLoading" @click="renew">{{ t('admin.tenants.subscriptionDialog.renew') }}</v-btn>
        </div>
      </div>

      <div class="d-flex flex-wrap ga-2 mb-4">
        <v-btn v-if="!isCancelled" size="small" variant="outlined" color="error" :loading="actionLoading" @click="cancel">
          {{ t('billingPage.actions.cancel') }}
        </v-btn>
        <v-btn v-else size="small" variant="outlined" color="success" :loading="actionLoading" @click="resume">
          {{ t('billingPage.actions.resume') }}
        </v-btn>

        <v-btn v-if="!isSuspended" size="small" variant="outlined" :loading="actionLoading" @click="suspend">
          {{ t('admin.tenants.subscriptionDialog.suspend') }}
        </v-btn>
        <v-btn v-else size="small" variant="outlined" color="success" :loading="actionLoading" @click="reactivate">
          {{ t('admin.tenants.subscriptionDialog.reactivate') }}
        </v-btn>
      </div>

      <v-divider class="mb-4" />

      <div class="text-subtitle-2 mb-2">{{ t('billingPage.paymentHistory') }}</div>
      <v-table density="compact">
        <thead>
          <tr>
            <th>{{ t('billingPage.paidAt') }}</th>
            <th>{{ t('fields.plan') }}</th>
            <th>{{ t('fields.total') }}</th>
            <th>{{ t('billingPage.recordedBy') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in payments" :key="payment.id">
            <td>{{ new Date(payment.paid_at).toLocaleDateString() }}</td>
            <td>{{ payment.plan_name }}</td>
            <td>${{ Number(payment.amount).toFixed(2) }}</td>
            <td>{{ payment.recorded_by?.name ?? t('billingPage.self') }}</td>
          </tr>
          <tr v-if="!payments.length">
            <td colspan="4" class="text-center text-medium-emphasis py-2">{{ t('common.noData') }}</td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </AppDialog>
</template>
