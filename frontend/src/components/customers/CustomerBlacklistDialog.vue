<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { blacklistSchema } from '@/utils/validators'
import { blacklistCustomerApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  customerId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const loading = ref(false)

async function onSubmit(values) {
  loading.value = true
  try {
    await blacklistCustomerApi(props.customerId, values.reason)
    appStore.notify(t('customers.messages.blacklistedSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('customers.dialogs.blacklistTitle')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppForm :schema="blacklistSchema" :initial-values="{ reason: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-textarea :model-value="values.reason" :label="`${t('fields.reason')} *`" rows="3" :error-messages="errors.reason" @update:model-value="setFieldValue('reason', $event)" />

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="error" variant="flat" :loading="loading">{{ t('customers.actions.blacklist') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
