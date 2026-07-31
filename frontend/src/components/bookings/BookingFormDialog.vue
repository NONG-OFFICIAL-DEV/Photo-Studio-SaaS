<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { bookingSchema } from '@/utils/validators'
import { createBookingApi, updateBookingApi } from '@/apis/booking.api'
import { getCustomersApi } from '@/apis/customer.api'
import { getUsersApi } from '@/apis/user.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  booking: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const customerOptions = ref([])
const customerSearchLoading = ref(false)
const users = ref([])

const BOOKING_TYPES = computed(() => [
  { title: t('bookings.types.wedding'), value: 'wedding' },
  { title: t('bookings.types.portrait'), value: 'portrait' },
  { title: t('bookings.types.family'), value: 'family' },
  { title: t('bookings.types.product'), value: 'product' },
  { title: t('bookings.types.passport'), value: 'passport' },
  { title: t('bookings.types.event'), value: 'event' },
  { title: t('bookings.types.other'), value: 'other' },
])

const isEdit = computed(() => Boolean(props.booking?.id))
const title = computed(() => (isEdit.value ? t('bookings.editBooking') : t('bookings.newBooking')))

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

const startDate = ref(null)
const startTime = ref('09:00')
const endDate = ref(null)
const endTime = ref('11:00')

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

  /*
   * These local refs (not the Form's own `values.starts_at`/`ends_at`)
   * drive the date pickers — AppDialog lazily mounts/unmounts its slot
   * content, so AppForm re-reads `initialValues` fresh on every open, but
   * BookingFormDialog itself stays mounted across opens, so these plain
   * refs must be re-derived from `props.booking` here on every open —
   * otherwise they'd stay frozen at whatever `props.booking` was the
   * first time this component ever mounted (usually null), leaving the
   * date pickers blank on every subsequent edit or calendar pre-fill.
   */
  const startsSplit = splitDateTime(props.booking?.starts_at)
  const endsSplit = splitDateTime(props.booking?.ends_at)
  startDate.value = startsSplit.date
  startTime.value = startsSplit.time || '09:00'
  endDate.value = endsSplit.date
  endTime.value = endsSplit.time || '11:00'

  if (props.booking?.customer) {
    customerOptions.value = [props.booking.customer]
  } else {
    loadInitialCustomers()
  }

  const { data } = await getUsersApi()
  users.value = data.data
})

/**
 * The autocomplete only searches on typed input (@update:search) — left
 * on its own that means an empty dropdown with no explanation the first
 * time the dialog opens, since nothing has been typed yet. Pre-populate
 * with a first page of customers so there's always something to see and
 * pick from immediately; typing still narrows it via searchCustomers().
 */
async function loadInitialCustomers() {
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function searchCustomers(term) {
  if (!term) return loadInitialCustomers()
  if (term.length < 2) return

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
      appStore.notify(t('bookings.messages.updatedSuccess'))
    } else {
      await createBookingApi(payload)
      appStore.notify(t('bookings.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'bookings.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="bookingSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>  
          <v-col cols="6">
            <v-text-field :model-value="values.title" :label="t('fields.title')" :error-messages="errors.title" @update:model-value="setFieldValue('title', $event)" />
          </v-col>
          <v-col cols="6">
            <v-autocomplete
              :model-value="values.customer_id"
              :label="`${t('fields.customer')} *`"
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
              :label="`${t('fields.type')} *`"
              :items="BOOKING_TYPES"
              :error-messages="errors.type"
              @update:model-value="setFieldValue('type', $event)"
            />
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.assigned_user_id"
              :label="t('fields.assignedTo')"
              clearable
              item-title="name"
              item-value="id"
              :items="users"
              @update:model-value="setFieldValue('assigned_user_id', $event)"
            />
          </v-col>
          <v-col cols="6" sm="6">
            <AppDatePicker
              :model-value="startDate"
              :label="`${t('fields.startDate')} *`"
              @update:model-value="(val) => { startDate = val; setFieldValue('starts_at', combineDateTime(val, startTime)) }"
            />
          </v-col>
          <v-col cols="6" sm="6">
            <v-text-field
              :model-value="startTime"
              type="time"
              :label="`${t('fields.startTime')} *`"
              @update:model-value="(val) => { startTime = val; setFieldValue('starts_at', combineDateTime(startDate, val)) }"
            />
          </v-col>
          <v-col cols="6" sm="6">
            <AppDatePicker
              :model-value="endDate"
              :label="`${t('fields.endDate')} *`"
              @update:model-value="(val) => { endDate = val; setFieldValue('ends_at', combineDateTime(val, endTime)) }"
            />
          </v-col>
          <v-col cols="6" sm="6">
            <v-text-field
              :model-value="endTime"
              type="time"
              :label="`${t('fields.endTime')} *`"
              @update:model-value="(val) => { endTime = val; setFieldValue('ends_at', combineDateTime(endDate, val)) }"
            />
          </v-col>
          <v-col v-if="errors.starts_at || errors.ends_at" cols="12" class="pt-0">
            <div class="text-caption text-error">{{ errors.starts_at || errors.ends_at }}</div>
          </v-col>

          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.location_type"
              :label="`${t('fields.location')} *`"
              :items="[{ title: t('bookings.locationTypes.studio'), value: 'studio' }, { title: t('bookings.locationTypes.onLocation'), value: 'on_location' }]"
              @update:model-value="setFieldValue('location_type', $event)"
            />
          </v-col>
          <v-col v-if="values.location_type === 'on_location'" cols="12" sm="6">
            <v-text-field :model-value="values.location_address" :label="`${t('fields.address')} *`" :error-messages="errors.location_address" @update:model-value="setFieldValue('location_address', $event)" />
          </v-col>

          <v-col cols="12">
            <v-textarea :model-value="values.notes" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" @update:model-value="setFieldValue('notes', $event)" />
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
