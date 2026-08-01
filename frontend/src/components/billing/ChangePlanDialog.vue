<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { getBillingPlansApi, changeBillingPlanApi } from '@/apis/billing.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { formatCurrency } from '@/utils/currencyFormat'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  currentPlanId: { type: String, default: null },
  currentBillingCycle: { type: String, default: 'monthly' },
  pendingPayment: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const changing = ref(false)
const errorMessage = ref('')
const plans = ref([])
const billingCycle = ref('monthly')
const selectedPlanId = ref(null)

const CYCLES = [
  { value: 'monthly', label: () => t('billingPage.cycles.monthly') },
  { value: 'quarterly', label: () => t('billingPage.cycles.quarterly') },
  { value: 'yearly', label: () => t('billingPage.cycles.yearly') },
]

watch(() => props.modelValue, async (open) => {
  if (!open) return
  errorMessage.value = ''
  billingCycle.value = props.currentBillingCycle || 'monthly'
  selectedPlanId.value = null
  loading.value = true
  try {
    const { data } = await getBillingPlansApi()
    plans.value = data.data
  } finally {
    loading.value = false
  }
})

// Switching cycle can leave a staged plan unable to sell that cycle —
// drop the selection rather than silently confirm-changing into a state
// the card grid shows as disabled.
watch(billingCycle, () => {
  const plan = plans.value.find(p => p.id === selectedPlanId.value)
  if (plan && !isAvailable(plan)) selectedPlanId.value = null
})

const CYCLE_MONTHS = { monthly: 1, quarterly: 3, yearly: 12 }

function priceFor(plan) {
  return plan[`price_${billingCycle.value}`]
}

// A plan may not sell every cycle (e.g. no yearly tier yet) — its price
// comes back null/0 for that cycle, same convention RenewDialog uses.
function isAvailable(plan) {
  const price = priceFor(plan)
  return price !== null && price !== undefined && Number(price) > 0
}

// What a longer cycle works out to per month, so a $470.40/yr plan reads
// as "≈$39.20/mo" next to its $49/mo sibling — the comparison a tenant
// actually needs to judge whether the cycle is worth committing to.
function monthlyEquivalent(plan) {
  if (!isAvailable(plan)) return null
  return Number(priceFor(plan)) / CYCLE_MONTHS[billingCycle.value]
}

// How much cheaper that works out to than paying monthly, as a whole
// percent — null (no badge) for monthly itself, a plan with no monthly
// price to compare against, or a cycle that isn't actually cheaper.
function savingsPercent(plan) {
  if (billingCycle.value === 'monthly') return null
  const monthly = Number(plan.price_monthly)
  const equivalent = monthlyEquivalent(plan)
  if (!monthly || equivalent === null) return null
  const percent = Math.round((1 - equivalent / monthly) * 100)
  return percent > 0 ? percent : null
}

// Headline hint above the cards — the best deal on offer for the selected
// cycle, so switching to Yearly immediately shows tenants what's in it for
// them before they even look at individual plans.
const maxSavingsPercent = computed(() => {
  const percents = plans.value.map(savingsPercent).filter(p => p !== null)
  return percents.length ? Math.max(...percents) : null
})

function isCurrentSelection(plan) {
  return plan.id === props.currentPlanId && billingCycle.value === props.currentBillingCycle
}

function selectPlan(plan) {
  if (isCurrentSelection(plan) || !isAvailable(plan)) return
  errorMessage.value = ''
  selectedPlanId.value = plan.id
}

const canConfirm = computed(() => {
  if (!selectedPlanId.value) return false
  const plan = plans.value.find(p => p.id === selectedPlanId.value)
  return Boolean(plan) && !isCurrentSelection(plan) && isAvailable(plan)
})

async function confirmChange() {
  if (!canConfirm.value) return

  changing.value = true
  errorMessage.value = ''
  try {
    await changeBillingPlanApi(selectedPlanId.value, billingCycle.value)
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
  <AppDialog :model-value="modelValue" :title="t('billingPage.actions.changePlan')" max-width="760" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>
    <v-alert v-else-if="pendingPayment" type="info" variant="tonal" class="mb-4">{{ t('billingPage.pendingPaymentHint') }}</v-alert>

    <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-5">
      <div>
        <div class="text-body-2 text-medium-emphasis">{{ t('billingPage.changePlanCycleHint') }}</div>
        <div v-if="maxSavingsPercent" class="text-body-2 font-weight-medium text-success">
          {{ t('billingPage.savePercentHint', { percent: maxSavingsPercent }) }}
        </div>
      </div>
      <v-btn-toggle v-model="billingCycle" mandatory density="comfortable" color="primary" variant="outlined" divided>
        <v-btn v-for="cycle in CYCLES" :key="cycle.value" :value="cycle.value" size="small">{{ cycle.label() }}</v-btn>
      </v-btn-toggle>
    </div>

    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-row v-else dense>
      <v-col v-for="plan in plans" :key="plan.id" cols="12" sm="6" md="4">
        <v-card
          variant="outlined"
          rounded="lg"
          :class="[
            'plan-card',
            {
              'plan-card--selected': plan.id === selectedPlanId,
              'plan-card--current': plan.id === currentPlanId,
              'plan-card--disabled': !isAvailable(plan),
            },
          ]"
          :disabled="changing || !isAvailable(plan)"
          class="pa-2 h-100 d-flex flex-column"
          @click="selectPlan(plan)"
        >
          <v-icon
            v-if="plan.id === selectedPlanId"
            icon="mdi-check-circle"
            color="primary"
            size="22"
            class="plan-card__check"
          />

          <v-card-title class="d-flex align-center justify-space-between px-3">
            {{ plan.name }}
            <v-chip v-if="plan.id === currentPlanId" size="x-small" color="primary" variant="flat" class="ms-2">{{ t('billingPage.currentPlan') }}</v-chip>
          </v-card-title>

          <v-card-text class="flex-grow-1">
            <template v-if="isAvailable(plan)">
              <div class="d-flex align-baseline ga-1">
                <span class="text-h4 font-weight-bold">{{ formatCurrency(priceFor(plan)) }}</span>
              </div>
              <span class="text-body-2 text-medium-emphasis">/ {{ t(`billingPage.cycles.${billingCycle}`) }}</span>
              <div v-if="billingCycle !== 'monthly'" class="d-flex align-center flex-wrap ga-2 mt-1 mb-3">
                <span class="text-caption text-medium-emphasis">
                  {{ t('billingPage.perMonthEquivalent', { amount: formatCurrency(monthlyEquivalent(plan)) }) }}
                </span>
                <v-chip v-if="savingsPercent(plan)" size="x-small" color="success" variant="flat">
                  {{ t('billingPage.savePercent', { percent: savingsPercent(plan) }) }}
                </v-chip>
              </div>
              <div v-else class="mb-3" />
            </template>
            <div v-else class="text-body-2 text-medium-emphasis mb-3">{{ t('billingPage.cycleNotAvailable') }}</div>

            <p class="text-body-2 text-medium-emphasis">{{ plan.description }}</p>

            <ul class="plan-features">
              <li>
                <v-icon icon="mdi-check" size="16" color="success" />
                {{ t('billingPage.limits.maxUsers', { count: plan.max_users ?? '∞' }) }}
              </li>
              <li>
                <v-icon icon="mdi-check" size="16" color="success" />
                {{ t('billingPage.limits.storage', { count: plan.storage_limit_gb ?? '∞' }) }}
              </li>
              <li>
                <v-icon icon="mdi-check" size="16" color="success" />
                {{ t('billingPage.limits.orders', { count: plan.monthly_order_limit ?? '∞' }) }}
              </li>
            </ul>
          </v-card-text>

          <v-card-actions>
            <v-btn
              block
              :variant="plan.id === selectedPlanId ? 'flat' : 'tonal'"
              :color="plan.id === selectedPlanId || plan.id === currentPlanId ? 'primary' : undefined"
              :disabled="isCurrentSelection(plan) || !isAvailable(plan)"
            >
              <template v-if="isCurrentSelection(plan)">{{ t('billingPage.currentPlan') }}</template>
              <template v-else-if="plan.id === selectedPlanId">{{ t('billingPage.selected') }}</template>
              <template v-else>{{ t('billingPage.actions.selectPlan') }}</template>
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <template #actions>
      <v-btn variant="text" :disabled="changing" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn color="primary" :disabled="!canConfirm" :loading="changing" @click="confirmChange">
        {{ t('billingPage.actions.confirmChangePlan') }}
      </v-btn>
    </template>
  </AppDialog>
</template>

<style scoped>
.plan-card {
  position: relative;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.plan-card:hover:not(.v-card--disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(var(--v-theme-on-surface), 0.12);
}

.plan-card--selected,
.plan-card--current {
  border-color: rgb(var(--v-theme-primary));
  border-width: 2px;
}

.plan-card--disabled {
  cursor: default;
}

.plan-card__check {
  position: absolute;
  top: 10px;
  right: 10px;
}

.plan-features {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.875rem;
}
</style>
