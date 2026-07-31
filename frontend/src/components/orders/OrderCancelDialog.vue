<script setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { cancelOrderSchema } from '@/utils/validators'
import { cancelOrderApi } from '@/apis/order.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  orderId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const loading = ref(false)
const formId = useId()

async function onSubmit(values) {
  loading.value = true
  try {
    await cancelOrderApi(props.orderId, values.reason)
    appStore.notify(t('orders.orderCancelled'))
    emit('saved')
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('orders.cancelOrder')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppForm :id="formId" :schema="cancelOrderSchema" :initial-values="{ reason: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-textarea :model-value="values.reason" :label="`${t('fields.reason')} *`" rows="3" :error-messages="errors.reason" @update:model-value="setFieldValue('reason', $event)" />
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('orders.keepOrder') }}</v-btn>
      <v-btn type="submit" :form="formId" color="error" variant="flat" :loading="loading">{{ t('orders.cancelOrder') }}</v-btn>
    </template>
  </AppDialog>
</template>
