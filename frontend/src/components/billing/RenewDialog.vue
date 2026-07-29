<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { renewBillingApi } from '@/apis/billing.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  plan: { type: Object, default: null },
  currentBillingCycle: { type: String, default: 'monthly' },
})

const emit = defineEmits(['update:modelValue', 'renewed', 'choose-plan'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const billingCycle = ref('monthly')

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    billingCycle.value = props.currentBillingCycle || 'monthly'
  }
})

// Free plans (e.g. the seeded Free Trial, price_monthly = 0) have nothing
// to renew — the backend rejects it outright. Filtering them out here
// means the dialog explains that up front instead of showing a payment
// option that always fails.
const cycles = computed(() => [
  { value: 'monthly', label: t('billingPage.cycles.monthly'), price: props.plan?.price_monthly },
  { value: 'quarterly', label: t('billingPage.cycles.quarterly'), price: props.plan?.price_quarterly },
  { value: 'yearly', label: t('billingPage.cycles.yearly'), price: props.plan?.price_yearly },
].filter(c => c.price !== null && c.price !== undefined && Number(c.price) > 0))

const isFreePlan = computed(() => cycles.value.length === 0)

function choosePlanInstead() {
  emit('update:modelValue', false)
  emit('choose-plan')
}

async function confirmRenew() {
  loading.value = true
  errorMessage.value = ''
  try {
    await renewBillingApi(billingCycle.value)
    appStore.notify(t('billingPage.messages.renewed'))
    emit('renewed')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('billingPage.messages.renewError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('billingPage.actions.renew')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <template v-if="isFreePlan">
      <p class="text-body-2 text-medium-emphasis mb-4">{{ t('billingPage.freePlanHint') }}</p>
      <div class="d-flex justify-end ga-2">
        <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" @click="choosePlanInstead">{{ t('billingPage.actions.changePlan') }}</v-btn>
      </div>
    </template>

    <template v-else>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ t('billingPage.renewHint') }}</p>

      <v-radio-group v-model="billingCycle" hide-details>
        <v-radio v-for="cycle in cycles" :key="cycle.value" :value="cycle.value">
          <template #label>
            <div class="d-flex justify-space-between" style="width: 100%">
              <span>{{ cycle.label }}</span>
              <span class="font-weight-bold">${{ Number(cycle.price).toFixed(2) }}</span>
            </div>
          </template>
        </v-radio>
      </v-radio-group>

      <div class="d-flex justify-end ga-2 mt-4">
        <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
        <v-btn color="primary" :loading="loading" @click="confirmRenew">{{ t('billingPage.actions.confirmPay') }}</v-btn>
      </div>
    </template>
  </AppDialog>
</template>
