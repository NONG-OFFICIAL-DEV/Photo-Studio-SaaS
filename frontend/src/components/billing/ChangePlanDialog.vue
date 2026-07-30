<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { getBillingPlansApi, changeBillingPlanApi } from '@/apis/billing.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currentPlanId: { type: String, default: null },
  pendingPayment: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const changing = ref(false)
const errorMessage = ref('')
const plans = ref([])

watch(() => props.modelValue, async (open) => {
  if (!open) return
  errorMessage.value = ''
  loading.value = true
  try {
    const { data } = await getBillingPlansApi()
    plans.value = data.data
  } finally {
    loading.value = false
  }
})

async function selectPlan(plan) {
  if (plan.id === props.currentPlanId) return

  changing.value = true
  errorMessage.value = ''
  try {
    await changeBillingPlanApi(plan.id)
    appStore.notify(t('billingPage.messages.planChanged'))
    emit('changed')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'billingPage.messages.planChangeError')
  } finally {
    changing.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('billingPage.actions.changePlan')" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>
    <v-alert v-else-if="pendingPayment" type="info" variant="tonal" class="mb-4">{{ t('billingPage.pendingPaymentHint') }}</v-alert>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-row v-else>
      <v-col v-for="plan in plans" :key="plan.id" cols="12" sm="6" md="4">
        <v-card
          variant="outlined"
          :color="plan.id === currentPlanId ? 'primary' : undefined"
          :disabled="changing"
          class="pa-2 h-100 d-flex flex-column"
          @click="selectPlan(plan)"
        >
          <v-card-title class="d-flex align-center justify-space-between">
            {{ plan.name }}
            <v-chip v-if="plan.id === currentPlanId" size="small" color="primary" variant="flat">{{ t('billingPage.currentPlan') }}</v-chip>
          </v-card-title>
          <v-card-text class="flex-grow-1">
            <div class="text-h6 font-weight-bold mb-2">${{ Number(plan.price_monthly).toFixed(2) }} <span class="text-caption text-medium-emphasis">/ {{ t('billingPage.cycles.monthly') }}</span></div>
            <p class="text-body-2 text-medium-emphasis">{{ plan.description }}</p>
            <ul class="text-body-2 pl-4">
              <li>{{ t('billingPage.limits.maxUsers', { count: plan.max_users ?? '∞' }) }}</li>
              <li>{{ t('billingPage.limits.storage', { count: plan.storage_limit_gb ?? '∞' }) }}</li>
              <li>{{ t('billingPage.limits.orders', { count: plan.monthly_order_limit ?? '∞' }) }}</li>
            </ul>
          </v-card-text>
          <v-card-actions>
            <v-btn block variant="tonal" :color="plan.id === currentPlanId ? 'primary' : undefined" :disabled="plan.id === currentPlanId" :loading="changing">
              {{ plan.id === currentPlanId ? t('billingPage.currentPlan') : t('billingPage.actions.selectPlan') }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </AppDialog>
</template>
