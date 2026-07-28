<script setup>
import { ref } from 'vue'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { cancelBookingSchema } from '@/utils/validators'
import { cancelBookingApi } from '@/apis/booking.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  bookingId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const appStore = useAppStore()
const loading = ref(false)

async function onSubmit(values) {
  loading.value = true
  try {
    await cancelBookingApi(props.bookingId, values.reason)
    appStore.notify('Booking cancelled.')
    emit('saved')
    emit('update:modelValue', false)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" title="Cancel Booking" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppForm :schema="cancelBookingSchema" :initial-values="{ reason: '' }" @submit="onSubmit">
      <template #default="{ errors }">
        <Field v-slot="{ field }" name="reason">
          <v-textarea v-bind="field" label="Reason *" rows="3" :error-messages="errors.reason" />
        </Field>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">Keep Booking</v-btn>
          <v-btn type="submit" color="error" variant="flat" :loading="loading">Cancel Booking</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
