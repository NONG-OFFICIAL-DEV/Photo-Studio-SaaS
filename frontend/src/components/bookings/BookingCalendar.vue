<script setup>
import { computed, onMounted, ref } from 'vue'
import {
  startOfMonth, endOfMonth, startOfWeek, endOfWeek, eachDayOfInterval,
  format, isSameMonth, isSameDay, addMonths, subMonths,
} from 'date-fns'
import { getBookingCalendarApi } from '@/apis/booking.api'

const emit = defineEmits(['day-click', 'booking-click'])

const STATUS_COLORS = {
  pending: 'warning',
  confirmed: 'info',
  in_progress: 'primary',
  completed: 'success',
  cancelled: 'error',
  no_show: 'error',
}

const currentMonth = ref(startOfMonth(new Date()))
const bookings = ref([])
const loading = ref(false)

const gridStart = computed(() => startOfWeek(startOfMonth(currentMonth.value)))
const gridEnd = computed(() => endOfWeek(endOfMonth(currentMonth.value)))

const days = computed(() => eachDayOfInterval({ start: gridStart.value, end: gridEnd.value }))

const weekdayLabels = computed(() => {
  const start = startOfWeek(new Date())
  return eachDayOfInterval({ start, end: endOfWeek(start) }).map(d => format(d, 'EEE'))
})

function bookingsForDay(day) {
  return bookings.value.filter(b => isSameDay(new Date(b.starts_at), day))
}

async function fetchBookings() {
  loading.value = true
  try {
    const { data } = await getBookingCalendarApi({
      start: format(gridStart.value, 'yyyy-MM-dd'),
      end: format(gridEnd.value, 'yyyy-MM-dd'),
    })
    bookings.value = data.data
  } finally {
    loading.value = false
  }
}

function goToPreviousMonth() {
  currentMonth.value = startOfMonth(subMonths(currentMonth.value, 1))
  fetchBookings()
}

function goToNextMonth() {
  currentMonth.value = startOfMonth(addMonths(currentMonth.value, 1))
  fetchBookings()
}

function goToToday() {
  currentMonth.value = startOfMonth(new Date())
  fetchBookings()
}

onMounted(fetchBookings)

defineExpose({ refresh: fetchBookings, bookings })
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-3">
      <div class="text-h6">{{ format(currentMonth, 'MMMM yyyy') }}</div>
      <div class="d-flex ga-1">
        <v-btn size="small" variant="outlined" @click="goToToday">Today</v-btn>
        <v-btn icon="mdi-chevron-left" size="small" variant="text" @click="goToPreviousMonth" />
        <v-btn icon="mdi-chevron-right" size="small" variant="text" @click="goToNextMonth" />
      </div>
    </div>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

    <div class="calendar-grid calendar-grid--header">
      <div v-for="label in weekdayLabels" :key="label" class="calendar-weekday">{{ label }}</div>
    </div>

    <div class="calendar-grid">
      <div
        v-for="day in days"
        :key="day.toISOString()"
        class="calendar-day"
        :class="{ 'calendar-day--muted': !isSameMonth(day, currentMonth), 'calendar-day--today': isSameDay(day, new Date()) }"
        @click="emit('day-click', { date: day, bookings: bookingsForDay(day) })"
      >
        <div class="calendar-day__number">{{ format(day, 'd') }}</div>
        <div class="calendar-day__bookings">
          <div
            v-for="booking in bookingsForDay(day).slice(0, 3)"
            :key="booking.id"
            class="calendar-booking"
            :class="`bg-${STATUS_COLORS[booking.status]}`"
            @click.stop="emit('booking-click', booking)"
          >
            {{ format(new Date(booking.starts_at), 'HH:mm') }} {{ booking.customer?.name }}
          </div>
          <div v-if="bookingsForDay(day).length > 3" class="text-caption text-medium-emphasis">
            +{{ bookingsForDay(day).length - 3 }} more
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.calendar-grid--header {
  margin-bottom: 4px;
}

.calendar-weekday {
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.7;
  padding: 4px 0;
}

.calendar-day {
  min-height: 96px;
  border: 1px solid rgba(128, 128, 128, 0.2);
  border-radius: 8px;
  padding: 4px;
  cursor: pointer;
  overflow: hidden;
}

.calendar-day--muted {
  opacity: 0.4;
}

.calendar-day--today {
  border-color: rgb(var(--v-theme-primary));
  border-width: 2px;
}

.calendar-day__number {
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 2px;
}

.calendar-day__bookings {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.calendar-booking {
  font-size: 0.7rem;
  color: white;
  border-radius: 4px;
  padding: 1px 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
