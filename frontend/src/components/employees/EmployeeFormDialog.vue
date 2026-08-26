<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppApiErrorAlert from '@/components/common/AppApiErrorAlert.vue'
import { employeeSchema } from '@/utils/validators'
import { createUserApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'
import { useBranchStore } from '@/stores/branches'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const branchStore = useBranchStore()

const loading = ref(false)
const submitError = ref(null)
const formId = useId()

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

const initialValues = {
  name: '',
  email: '',
  phone: '',
  creation_mode: 'invite',
  password: '',
  branch_id: null,
  role: 'viewer',
  pay_type: 'salary',
  base_pay: null,
  commission_rate: null,
}

watch(() => props.modelValue, (open) => {
  if (open) {
    submitError.value = null
    branchStore.fetch()
  }
})

async function onSubmit(values) {
  loading.value = true
  submitError.value = null

  // creation_mode is a frontend-only toggle, not a backend field — and in
  // invite mode, password is left blank in the form, so it's omitted
  // entirely rather than sent as an empty string (an absent password is
  // exactly what tells the backend to send an invite).
  const { creation_mode, password, ...payload } = values
  if (creation_mode === 'password') {
    payload.password = password
  }

  try {
    await createUserApi(payload)
    appStore.notify(t(creation_mode === 'invite' ? 'employees.messages.inviteSentSuccess' : 'employees.messages.createdSuccess'))
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
  <AppDialog :model-value="modelValue" :title="t('employees.newEmployee')" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <AppApiErrorAlert :error="submitError" fallback-key="employees.messages.createError" />

    <AppForm :id="formId" :schema="employeeSchema" :initial-values="initialValues" @submit="onSubmit">
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
          <v-col cols="12">
            <v-btn-toggle
              :model-value="values.creation_mode"
              color="primary"
              variant="outlined"
              density="comfortable"
              mandatory
              divided
              @update:model-value="setFieldValue('creation_mode', $event)"
            >
              <v-btn value="invite">{{ t('employees.sendInvite') }}</v-btn>
              <v-btn value="password">{{ t('employees.setPasswordNow') }}</v-btn>
            </v-btn-toggle>
          </v-col>
          <v-col v-if="values.creation_mode === 'password'" cols="12" sm="6">
            <v-text-field
              :model-value="values.password"
              :label="`${t('auth.password')} *`"
              type="password"
              autocomplete="new-password"
              :error-messages="errors.password"
              :hint="t('employees.passwordHint')"
              persistent-hint
              @update:model-value="setFieldValue('password', $event)"
            />
          </v-col>
          <v-col v-else cols="12">
            <p class="text-body-2 text-medium-emphasis">{{ t('employees.inviteHint') }}</p>
          </v-col>
          <v-col v-if="branchStore.branches.length > 1" cols="12">
            <v-select
              :model-value="values.branch_id"
              :label="`${t('fields.branch')} *`"
              :items="branchStore.branches"
              item-title="name"
              item-value="id"
              :error-messages="errors.branch_id"
              @update:model-value="setFieldValue('branch_id', $event)"
            />
          </v-col>
          <v-col cols="12">
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
        </v-row>
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
