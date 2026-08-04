<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { submitPaymentClaimApi } from '@/apis/billing.api'
import { translateApiMessage } from '@/utils/apiMessages'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  defaultAmount: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:modelValue', 'submitted'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const claimedAmount = ref(null)
const note = ref('')
const receipt = ref(null)
const receiptInput = ref(null)

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    claimedAmount.value = props.defaultAmount ?? null
    note.value = ''
    receipt.value = null
  }
})

function triggerReceiptUpload() {
  receiptInput.value?.click()
}

function onReceiptSelected(event) {
  receipt.value = event.target.files?.[0] ?? null
}

const isAmountValid = computed(() => Number(claimedAmount.value) > 0)

async function handleSubmit() {
  loading.value = true
  errorMessage.value = ''
  try {
    await submitPaymentClaimApi({ claimed_amount: claimedAmount.value, note: note.value, receipt: receipt.value })
    appStore.notify(t('billingPage.paymentClaim.messages.submitted'))
    emit('submitted')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'billingPage.paymentClaim.messages.submitError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('billingPage.paymentClaim.dialogTitle')"
    max-width="480"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('billingPage.paymentClaim.hint') }}</p>

    <v-text-field
      v-model="claimedAmount"
      :label="`${t('billingPage.paymentClaim.amount')} *`"
      type="number"
      step="0.01"
      prefix="$"
      class="mb-2"
    />
    <v-textarea
      v-model="note"
      :label="t('billingPage.paymentClaim.note')"
      rows="3"
      class="mb-2"
    />

    <input ref="receiptInput" type="file" accept="image/*" class="d-none" @change="onReceiptSelected" />
    <v-btn variant="outlined" size="small" prepend-icon="mdi-receipt-text-outline" @click="triggerReceiptUpload">
      {{ receipt ? receipt.name : t('billingPage.paymentClaim.uploadReceipt') }}
    </v-btn>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn color="primary" variant="flat" :loading="loading" :disabled="!isAmountValid" @click="handleSubmit">
        {{ t('billingPage.paymentClaim.submit') }}
      </v-btn>
    </template>
  </AppDialog>
</template>
