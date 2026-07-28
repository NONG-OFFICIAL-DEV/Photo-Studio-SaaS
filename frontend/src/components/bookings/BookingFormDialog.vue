<script setup>
import { computed, ref, watch } from 'vue'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { bookingSchema } from '@/utils/validators'
import { createBookingApi, updateBookingApi } from '@/apis/booking.api'
import { getCustomersApi } from '@/apis/customer.api'
import { getUsersApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  booking: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')

const customerOptions = ref([])
const customerSearchLoading = ref(false)
const users = ref([])

const BOOKING_TYPES = [
  { title: 'Wedding', value: 'wedding' },
  { title: 'Portrait', value: 'portrait' },
  { title: 'Family', value: 'family' },
  { title: 'Product', value: 'product' },
  { title: 'Passport', value: 'passport' },
  { title: 'Event', value: 'event' },
  { title: 'Other', value: 'other' },
]

const isEdit = computed(() => Boolean(props.booking?.id))
const title = computed(() => (isEdit.value ? 'Edit Booking' : 'New Booking'))

function splitDateTime(iso) {
  if (!iso) return { date: null, time: '' }
  const d = new Date(iso)
  const pad = n => String(n).padStart(2, '0')
  return {
    date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
    time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
  }
}

function combineDateTime(date, time) {
  if (!date || !time) return null
  return new Date(`${date}T${time}:00`).toISOString()
}

const startsSplit = splitDateTime(props.booking?.starts_at)
const endsSplit = splitDateTime(props.booking?.ends_at)

const startDate = ref(startsSplit.date)
const startTime = ref(startsSplit.time || '09:00')
const endDate = ref(endsSplit.date)
const endTime = ref(endsSplit.time || '11:00')

const initialValues = computed(() => ({
  customer_id: props.booking?.customer?.id ?? null,
  assigned_user_id: props.booking?.assigned_user?.id ?? null,
  type: props.booking?.type ?? null,
  title: props.booking?.title ?? '',
  notes: props.booking?.notes ?? '',
  location_type: props.booking?.location_type ?? 'studio',
  location_address: props.booking?.location_address ?? '',
  starts_at: props.booking?.starts_at ?? null,
  ends_at: props.booking?.ends_at ?? null,
}))

watch(() => props.modelValue, async (open) => {
  if (!open) return

  errorMessage.value = ''

  if (props.booking?.customer) {
    customerOptions.value = [props.booking.customer]
  }

  const { data } = await getUsersApi()
  users.value = data.data
})

async function searchCustomers(term) {
  if (!term || term.length < 2) return
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ search: term, perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  const payload = { ...values }

  try {
    if (isEdit.value) {
      await updateBookingApi(props.booking.id, payload)
      appStore.notify('Booking updated successfully.')
    } else {
      await createBookingApi(payload)
      appStore.notify('Booking created successfully.')
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to save booking.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="bookingSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12">
            <v-autocomplete
              :model-value="values.customer_id"
              label="Customer *"
              item-title="name"
              item-value="id"
              :items="customerOptions"
              :loading="customerSearchLoading"
              :error-messages="errors.customer_id"
              no-filter
              clearable
              @update:search="searchCustomers"
              @update:model-value="setFieldValue('customer_id', $event)"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps" :subtitle="item.raw.phone" />
              </template>
            </v-autocomplete>
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.type"
              label="Type *"
              :items="BOOKING_TYPES"
              :error-messages="errors.type"
              @update:model-value="setFieldValue('type', $event)"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.assigned_user_id"
              label="Assign to"
              clearable
              item-title="name"
              item-value="id"
              :items="users"
              @update:model-value="setFieldValue('assigned_user_id', $event)"
            />
          </v-col>

          <v-col cols="12">
            <Field v-slot="{ field }" name="title">
              <v-text-field v-bind="field" label="Title" :error-messages="errors.title" />
            </Field>
          </v-col>

          <v-col cols="6" sm="3">
            <AppDatePicker
              :model-value="startDate"
              label="Start date *"
              @update:model-value="(val) => { startDate = val; setFieldValue('starts_at', combineDateTime(val, startTime)) }"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field
              :model-value="startTime"
              type="time"
              label="Start time *"
              @update:model-value="(val) => { startTime = val; setFieldValue('starts_at', combineDateTime(startDate, val)) }"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <AppDatePicker
              :model-value="endDate"
              label="End date *"
              @update:model-value="(val) => { endDate = val; setFieldValue('ends_at', combineDateTime(val, endTime)) }"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field
              :model-value="endTime"
              type="time"
              label="End time *"
              @update:model-value="(val) => { endTime = val; setFieldValue('ends_at', combineDateTime(endDate, val)) }"
            />
          </v-col>
          <v-col v-if="errors.starts_at || errors.ends_at" cols="12" class="pt-0">
            <div class="text-caption text-error">{{ errors.starts_at || errors.ends_at }}</div>
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.location_type"
              label="Location *"
              :items="[{ title: 'Studio', value: 'studio' }, { title: 'On Location', value: 'on_location' }]"
              @update:model-value="setFieldValue('location_type', $event)"
            />
          </v-col>
          <v-col v-if="values.location_type === 'on_location'" cols="12" sm="6">
            <Field v-slot="{ field }" name="location_address">
              <v-text-field v-bind="field" label="Address *" :error-messages="errors.location_address" />
            </Field>
          </v-col>

          <v-col cols="12">
            <Field v-slot="{ field }" name="notes">
              <v-textarea v-bind="field" label="Notes" rows="2" :error-messages="errors.notes" />
            </Field>
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">Cancel</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">Save</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
