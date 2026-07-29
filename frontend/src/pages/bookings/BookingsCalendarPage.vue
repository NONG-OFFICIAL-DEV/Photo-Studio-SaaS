<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { format, isSameDay } from 'date-fns'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import BookingCalendar from '@/components/bookings/BookingCalendar.vue'
import BookingFormDialog from '@/components/bookings/BookingFormDialog.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const STATUS_MAP = computed(() => ({
  pending: { color: 'warning', label: t('bookings.status.pending') },
  confirmed: { color: 'info', label: t('bookings.status.confirmed') },
  in_progress: { color: 'primary', label: t('bookings.status.inProgress') },
  completed: { color: 'success', label: t('bookings.status.completed') },
  cancelled: { color: 'error', label: t('bookings.status.cancelled') },
  no_show: { color: 'error', label: t('bookings.status.noShow') },
}))

const calendarRef = ref(null)
const dayDialog = ref(false)
const selectedDay = ref(null)
const dayBookings = ref([])

const formDialog = ref(false)
const editingBooking = ref(null)

const canCreate = computed(() => auth.hasPermission('bookings.create'))

function onDayClick({ date, bookings }) {
  selectedDay.value = date
  dayBookings.value = bookings
  dayDialog.value = true
}

function onBookingClick(booking) {
  editingBooking.value = booking
  formDialog.value = true
}

function createForSelectedDay() {
  dayDialog.value = false
  editingBooking.value = selectedDay.value
    ? { starts_at: new Date(selectedDay.value.setHours(9, 0)).toISOString(), ends_at: new Date(selectedDay.value.setHours(11, 0)).toISOString() }
    : null
  formDialog.value = true
}

async function onSaved() {
  await calendarRef.value?.refresh()

  if (dayDialog.value && selectedDay.value) {
    dayBookings.value = calendarRef.value.bookings.filter(b => isSameDay(new Date(b.starts_at), selectedDay.value))
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('bookings.calendarTitle')" :subtitle="t('bookings.calendarSubtitle')">
      <template #actions>
        <v-btn variant="outlined" prepend-icon="mdi-format-list-bulleted" :to="{ name: 'bookings' }">{{ t('bookings.listView') }}</v-btn>
      </template>
    </AppToolbar>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <BookingCalendar ref="calendarRef" @day-click="onDayClick" @booking-click="onBookingClick" />
    </v-card>

    <AppDialog v-model="dayDialog" :title="selectedDay ? format(selectedDay, 'MMMM d, yyyy') : ''" max-width="480">
      <div v-if="!dayBookings.length" class="text-body-2 text-medium-emphasis mb-4">{{ t('bookings.noBookingsOnDay') }}</div>

      <v-list v-else density="compact" class="mb-4">
        <v-list-item
          v-for="booking in dayBookings"
          :key="booking.id"
          :title="booking.customer?.name"
          :subtitle="`${format(new Date(booking.starts_at), 'HH:mm')} - ${format(new Date(booking.ends_at), 'HH:mm')}`"
          @click="onBookingClick(booking)"
        >
          <template #append>
            <AppStatusChip :status="booking.status" :map="STATUS_MAP" size="small" />
          </template>
        </v-list-item>
      </v-list>

      <v-btn v-if="canCreate" block color="primary" variant="flat" prepend-icon="mdi-plus" @click="createForSelectedDay">
        {{ t('bookings.addBooking') }}
      </v-btn>
    </AppDialog>

    <BookingFormDialog v-model="formDialog" :booking="editingBooking" @saved="onSaved" />
  </div>
</template>
