<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppApiErrorAlert from '@/components/common/AppApiErrorAlert.vue'
import { employmentSchema } from '@/utils/validators'
import { updateUserEmploymentApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const submitError = ref(null)
const formId = useId()

const PAY_TYPES = computed(() => [
  { title: t('employees.payTypes.salary'), value: 'salary' },
  { title: t('employees.payTypes.hourly'), value: 'hourly' },
])

const initialValues = computed(() => ({
  pay_type: props.employee?.pay_type ?? 'salary',
  base_pay: props.employee?.base_pay ?? null,
  commission_rate: props.employee?.commission_rate ?? null,
}))

watch(() => props.modelValue, (open) => {
  if (open) submitError.value = null
})

async function onSubmit(values) {
  loading.value = true
  submitError.value = null

  try {
    await updateUserEmploymentApi(props.employee.id, values)
    appStore.notify(t('employees.messages.updatedSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    submitError.value = error
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('employees.editPayProfile', { name: employee?.name })" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppApiErrorAlert :error="submitError" fallback-key="employees.messages.saveError" />

    <AppForm :id="formId" :schema="employmentSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-select
          :model-value="values.pay_type"
          :label="`${t('employees.payType')} *`"
          :items="PAY_TYPES"
          :error-messages="errors.pay_type"
          class="mb-2"
          @update:model-value="setFieldValue('pay_type', $event)"
        />

        <v-text-field
          :model-value="values.base_pay"
          :label="values.pay_type === 'hourly' ? t('employees.hourlyRate') : t('employees.monthlySalary')"
          type="number"
          step="0.01"
          prefix="$"
          :error-messages="errors.base_pay"
          class="mb-2"
          @update:model-value="setFieldValue('base_pay', $event)"
        />

        <v-text-field :model-value="values.commission_rate" :label="t('employees.commissionRate')" type="number" step="0.01" suffix="%" :error-messages="errors.commission_rate" @update:model-value="setFieldValue('commission_rate', $event)" />
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
