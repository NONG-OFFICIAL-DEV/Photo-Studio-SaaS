<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
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
const errorMessage = ref('')

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
  if (open) errorMessage.value = ''
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await updateUserEmploymentApi(props.employee.id, values)
    appStore.notify(t('employees.messages.updatedSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('employees.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('employees.editPayProfile', { name: employee?.name })" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="employmentSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-select
          :model-value="values.pay_type"
          :label="`${t('employees.payType')} *`"
          :items="PAY_TYPES"
          :error-messages="errors.pay_type"
          class="mb-2"
          @update:model-value="setFieldValue('pay_type', $event)"
        />

        <Field v-slot="{ field }" name="base_pay">
          <v-text-field
            v-bind="field"
            :label="values.pay_type === 'hourly' ? t('employees.hourlyRate') : t('employees.monthlySalary')"
            type="number"
            step="0.01"
            prefix="$"
            :error-messages="errors.base_pay"
            class="mb-2"
          />
        </Field>

        <Field v-slot="{ field }" name="commission_rate">
          <v-text-field v-bind="field" :label="t('employees.commissionRate')" type="number" step="0.01" suffix="%" :error-messages="errors.commission_rate" />
        </Field>

        <div class="d-flex justify-end ga-2 mt-4">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
