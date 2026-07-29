<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { commissionEntrySchema } from '@/utils/validators'
import { createCommissionEntryApi } from '@/apis/commission.api'
import { getUsersApi } from '@/apis/user.api'
import { getOrdersApi } from '@/apis/order.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const users = ref([])
const orders = ref([])
const orderSearchLoading = ref(false)

watch(() => props.modelValue, async (open) => {
  if (open) {
    errorMessage.value = ''
    const { data } = await getUsersApi()
    users.value = data.data
    loadInitialOrders()
  }
})

async function loadInitialOrders() {
  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ perPage: 20 })
    orders.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

async function searchOrders(term) {
  if (!term) return loadInitialOrders()
  if (term.length < 2) return

  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ search: term, perPage: 20 })
    orders.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await createCommissionEntryApi(values)
    appStore.notify(t('commissions.messages.createdSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('commissions.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('commissions.newEntry')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm
      :schema="commissionEntrySchema"
      :initial-values="{ user_id: null, order_id: null, amount: null, earned_date: null, notes: '' }"
      @submit="onSubmit"
    >
      <template #default="{ errors, values, setFieldValue }">
        <v-select
          :model-value="values.user_id"
          :label="`${t('fields.assignedTo')} *`"
          item-title="name"
          item-value="id"
          :items="users"
          :error-messages="errors.user_id"
          class="mb-2"
          @update:model-value="setFieldValue('user_id', $event)"
        />

        <v-autocomplete
          :model-value="values.order_id"
          :label="t('commissions.linkedOrder')"
          clearable
          item-value="id"
          :items="orders.map(o => ({ id: o.id, title: `${o.customer?.name ?? ''} — $${o.total}` }))"
          :loading="orderSearchLoading"
          no-filter
          class="mb-2"
          @update:search="searchOrders"
          @update:model-value="setFieldValue('order_id', $event)"
        />

        <Field v-slot="{ field }" name="amount">
          <v-text-field v-bind="field" :label="`${t('invoices.amount')} *`" type="number" step="0.01" prefix="$" :error-messages="errors.amount" class="mb-2" />
        </Field>

        <AppDatePicker
          :model-value="values.earned_date"
          :label="`${t('commissions.earnedDate')} *`"
          :error-messages="errors.earned_date"
          :clearable="false"
          class="mb-2"
          @update:model-value="setFieldValue('earned_date', $event)"
        />

        <Field v-slot="{ field }" name="notes">
          <v-textarea v-bind="field" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" />
        </Field>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
