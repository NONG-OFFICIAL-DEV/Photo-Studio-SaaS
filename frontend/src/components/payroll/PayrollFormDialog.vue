<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { payrollEntrySchema } from '@/utils/validators'
import { createPayrollEntryApi } from '@/apis/payroll.api'
import { getUsersApi } from '@/apis/user.api'
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

watch(() => props.modelValue, async (open) => {
  if (open) {
    errorMessage.value = ''
    const { data } = await getUsersApi()
    users.value = data.data
  }
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    await createPayrollEntryApi(values)
    appStore.notify(t('payroll.messages.createdSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('payroll.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('payroll.newEntry')" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>
    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('payroll.autoComputeHint') }}</p>

    <AppForm
      :schema="payrollEntrySchema"
      :initial-values="{ user_id: null, period_label: '', period_start: null, period_end: null, base_pay: null, commission_total: null, deductions: null, notes: '' }"
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

        <Field v-slot="{ field }" name="period_label">
          <v-text-field v-bind="field" :label="`${t('payroll.periodLabel')} *`" :error-messages="errors.period_label" class="mb-2" placeholder="e.g. July 2026" />
        </Field>

        <v-row>
          <v-col cols="6">
            <AppDatePicker
              :model-value="values.period_start"
              :label="`${t('payroll.periodStart')} *`"
              :error-messages="errors.period_start"
              :clearable="false"
              @update:model-value="setFieldValue('period_start', $event)"
            />
          </v-col>
          <v-col cols="6">
            <AppDatePicker
              :model-value="values.period_end"
              :label="`${t('payroll.periodEnd')} *`"
              :error-messages="errors.period_end"
              :clearable="false"
              @update:model-value="setFieldValue('period_end', $event)"
            />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="4">
            <Field v-slot="{ field }" name="base_pay">
              <v-text-field v-bind="field" :label="t('payroll.basePayOverride')" type="number" step="0.01" prefix="$" :error-messages="errors.base_pay" />
            </Field>
          </v-col>
          <v-col cols="4">
            <Field v-slot="{ field }" name="commission_total">
              <v-text-field v-bind="field" :label="t('payroll.commissionOverride')" type="number" step="0.01" prefix="$" :error-messages="errors.commission_total" />
            </Field>
          </v-col>
          <v-col cols="4">
            <Field v-slot="{ field }" name="deductions">
              <v-text-field v-bind="field" :label="t('payroll.deductions')" type="number" step="0.01" prefix="$" :error-messages="errors.deductions" />
            </Field>
          </v-col>
        </v-row>

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
