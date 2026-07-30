<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { attendanceSchema } from '@/utils/validators'
import { createAttendanceRecordApi } from '@/apis/attendance.api'
import { getUsersApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const users = ref([])

const STATUS_ITEMS = computed(() => [
  { title: t('attendance.status.present'), value: 'present' },
  { title: t('attendance.status.late'), value: 'late' },
  { title: t('attendance.status.absent'), value: 'absent' },
])

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
    await createAttendanceRecordApi(values)
    appStore.notify(t('attendance.messages.createdSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'attendance.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('attendance.newRecord')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="attendanceSchema" :initial-values="{ user_id: null, date: null, status: 'absent', reason: '' }" @submit="onSubmit">
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

        <AppDatePicker
          :model-value="values.date"
          :label="`${t('fields.startDate')} *`"
          :error-messages="errors.date"
          :clearable="false"
          class="mb-2"
          @update:model-value="setFieldValue('date', $event)"
        />

        <v-select
          :model-value="values.status"
          :label="`${t('fields.status')} *`"
          :items="STATUS_ITEMS"
          :error-messages="errors.status"
          class="mb-2"
          @update:model-value="setFieldValue('status', $event)"
        />

        <v-textarea :model-value="values.reason" :label="t('fields.reason')" rows="2" :error-messages="errors.reason" @update:model-value="setFieldValue('reason', $event)" />

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
