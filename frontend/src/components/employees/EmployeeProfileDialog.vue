<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppApiErrorAlert from '@/components/common/AppApiErrorAlert.vue'
import { employeeProfileSchema, employmentSchema } from '@/utils/validators'
import { updateUserProfileApi, updateUserEmploymentApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'
import { useBranchStore } from '@/stores/branches'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const branchStore = useBranchStore()

const loading = ref(false)
const submitError = ref(null)
const formId = useId()

// One form, two backend endpoints — profile (name/email/phone/role) and
// pay (pay_type/base_pay/commission_rate) are deliberately separate
// endpoints on the backend (see UserController::updateProfile() vs
// update()) so the already-working pay-edit flow stays untouched.
const editSchema = employeeProfileSchema.concat(employmentSchema)

const ROLES = computed(() => [
  { title: t('employees.roles.manager'), value: 'manager' },
  { title: t('employees.roles.photographer'), value: 'photographer' },
  { title: t('employees.roles.editor'), value: 'editor' },
  { title: t('employees.roles.cashier'), value: 'cashier' },
  { title: t('employees.roles.receptionist'), value: 'receptionist' },
  { title: t('employees.roles.viewer'), value: 'viewer' },
])

const PAY_TYPES = computed(() => [
  { title: t('employees.payTypes.salary'), value: 'salary' },
  { title: t('employees.payTypes.hourly'), value: 'hourly' },
])

const initialValues = computed(() => ({
  name: props.employee?.name ?? '',
  email: props.employee?.email ?? '',
  phone: props.employee?.phone ?? '',
  role: props.employee?.roles?.[0] ?? 'viewer',
  pay_type: props.employee?.pay_type ?? 'salary',
  base_pay: props.employee?.base_pay ?? null,
  commission_rate: props.employee?.commission_rate ?? null,
  branch_id: props.employee?.branch_id ?? null,
}))

watch(() => props.modelValue, (open) => {
  if (open) {
    submitError.value = null
    branchStore.fetch()
  }
})

async function onSubmit(values) {
  loading.value = true
  submitError.value = null

  try {
    await Promise.all([
      updateUserProfileApi(props.employee.id, {
        name: values.name,
        email: values.email,
        phone: values.phone,
        role: values.role,
      }),
      updateUserEmploymentApi(props.employee.id, {
        pay_type: values.pay_type,
        base_pay: values.base_pay,
        commission_rate: values.commission_rate,
        branch_id: values.branch_id,
      }),
    ])
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
  <AppDialog :model-value="modelValue" :title="t('employees.editEmployee', { name: employee?.name })" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <AppApiErrorAlert :error="submitError" fallback-key="employees.messages.saveError" />

    <AppForm :id="formId" :schema="editSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.email" :label="`${t('fields.email')} *`" type="email" :error-messages="errors.email" @update:model-value="setFieldValue('email', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.phone" :label="t('fields.phone')" :error-messages="errors.phone" @update:model-value="setFieldValue('phone', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.role"
              :label="`${t('employees.role')} *`"
              :items="ROLES"
              :error-messages="errors.role"
              @update:model-value="setFieldValue('role', $event)"
            />
          </v-col>

          <v-col cols="12" sm="4">
            <v-select
              :model-value="values.pay_type"
              :label="`${t('employees.payType')} *`"
              :items="PAY_TYPES"
              :error-messages="errors.pay_type"
              @update:model-value="setFieldValue('pay_type', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              :model-value="values.base_pay"
              :label="values.pay_type === 'hourly' ? t('employees.hourlyRate') : t('employees.monthlySalary')"
              type="number"
              step="0.01"
              prefix="$"
              :error-messages="errors.base_pay"
              @update:model-value="setFieldValue('base_pay', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.commission_rate" :label="t('employees.commissionRate')" type="number" step="0.01" suffix="%" :error-messages="errors.commission_rate" @update:model-value="setFieldValue('commission_rate', $event)" />
          </v-col>
          <v-col v-if="branchStore.branches.length > 1" cols="12" sm="6">
            <v-select
              :model-value="values.branch_id"
              :label="t('fields.branch')"
              clearable
              item-title="name"
              item-value="id"
              :items="branchStore.branches"
              :error-messages="errors.branch_id"
              @update:model-value="setFieldValue('branch_id', $event)"
            />
          </v-col>
        </v-row>
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
