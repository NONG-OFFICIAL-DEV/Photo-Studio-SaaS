<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { format, isSameDay } from 'date-fns'
import { getBookingCalendarApi } from '@/apis/booking.api'
import { useDateFnsLocale } from '@/utils/dateFnsLocale'

const { t } = useI18n()
const dateFnsLocale = useDateFnsLocale()
const emit = defineEmits(['day-click', 'booking-click'])

const STATUS_COLORS = {
  pending: 'warning',
  confirmed: 'info',
  in_progress: 'primary',
  completed: 'success',
  cancelled: 'error',
  no_show: 'error',
}

const VIEW_TYPES = [
  { value: 'month', icon: 'mdi-calendar-month-outline', label: () => t('bookings.calendarViews.month') },
  { value: 'week', icon: 'mdi-calendar-week-begin', label: () => t('bookings.calendarViews.week') },
  { value: 'day', icon: 'mdi-calendar-clock-outline', label: () => t('bookings.calendarViews.day') },
]

// Kept in sync with the :first-interval/:interval-count/:interval-height
// props below — nowY() positions the current-time line from these directly
// instead of the calendar instance's own timeToY(), which VCalendar fails
// to forward from its dynamically-swapped VCalendarDaily child (its `in`
// check passes but the matching property read comes back undefined).
const FIRST_INTERVAL_HOUR = 7
const INTERVAL_COUNT = 15
const INTERVAL_HEIGHT = 56

const calendarRef = ref(null)
const viewType = ref('week')
const focusDate = ref(new Date())
const bookings = ref([])
const loading = ref(false)
const visibleRange = ref({ start: null, end: null })

/*
 * v-calendar formats its own weekday/month/title text via plain
 * Intl.DateTimeFormat internally (bypassing Vuetify's pluggable date
 * adapter — see plugins/khmerDateAdapter.js, which only covers
 * v-date-picker) — and real Chrome's Intl has no Khmer data for month or
 * weekday names despite Node reporting otherwise, so this needs the same
 * treatment date-fns already gives the old hand-rolled calendar: weekday
 * names through date-fns' own translated locale data, and month/year
 * kept numeric (per this app's standing "no month-name text" rule) so it
 * never needs translating in the first place.
 */
function weekdayFormat(timestamp, short) {
  const day = new Date(timestamp.year, timestamp.month - 1, timestamp.day)
  return format(day, short ? 'EEEEEE' : 'EEEE', { locale: dateFnsLocale.value })
}

function monthFormat(timestamp) {
  return format(new Date(timestamp.year, timestamp.month - 1, timestamp.day), 'MM/yyyy')
}

const title = computed(() => {
  const { start, end } = visibleRange.value
  if (!start) return ''
  if (viewType.value === 'month') return format(focusDate.value, 'MM/yyyy')
  if (start.date === end.date) return format(focusDate.value, 'dd/MM/yyyy')
  return `${format(new Date(start.year, start.month - 1, start.day), 'dd/MM')} – ${format(new Date(end.year, end.month - 1, end.day), 'dd/MM/yyyy')}`
})

const calendarEvents = computed(() =>
  bookings.value.map((booking) => ({
    ...booking,
    name: `${booking.customer?.name ?? ''} — ${booking.title || booking.type}`,
    start: new Date(booking.starts_at),
    end: new Date(booking.ends_at),
    color: STATUS_COLORS[booking.status],
    timed: true,
  })),
)

function bookingsForDate(date) {
  return bookings.value.filter((b) => isSameDay(new Date(b.starts_at), date))
}

async function fetchBookings(start, end) {
  loading.value = true
  try {
    const { data } = await getBookingCalendarApi({ start, end })
    bookings.value = data.data
  } finally {
    loading.value = false
  }
}

function onRangeChange({ start, end }) {
  visibleRange.value = { start, end }
  fetchBookings(start.date, end.date)
}

function goToToday() {
  focusDate.value = new Date()
}

function onDayClick(_nativeEvent, day) {
  const date = new Date(day.year, day.month - 1, day.day, day.hour ?? 0, day.minute ?? 0)
  emit('day-click', {
    date,
    time: day.hasTime ? format(date, 'HH:mm') : null,
    bookings: bookingsForDate(date),
  })
}

function onEventClick(nativeEvent, { event }) {
  nativeEvent?.stopPropagation()
  emit('booking-click', event)
}

const now = ref(new Date())

function nowY() {
  const minutes = now.value.getHours() * 60 + now.value.getMinutes()
  const firstMinute = FIRST_INTERVAL_HOUR * 60
  const totalMinutes = INTERVAL_COUNT * 60
  const bodyHeight = INTERVAL_COUNT * INTERVAL_HEIGHT
  const fraction = (minutes - firstMinute) / totalMinutes
  if (fraction < 0 || fraction > 1) return '-10px'
  return `${fraction * bodyHeight}px`
}

let nowInterval = null
onMounted(() => {
  nowInterval = window.setInterval(() => { now.value = new Date() }, 60 * 1000)
})
onUnmounted(() => window.clearInterval(nowInterval))

defineExpose({
  refresh: () => visibleRange.value.start && fetchBookings(visibleRange.value.start.date, visibleRange.value.end.date),
  bookings,
})
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
      <div class="d-flex align-center ga-1">
        <v-btn size="small" variant="outlined" @click="goToToday">{{ t('bookings.today') }}</v-btn>
        <v-btn icon="mdi-chevron-left" size="small" variant="text" @click="calendarRef?.prev()" />
        <v-btn icon="mdi-chevron-right" size="small" variant="text" @click="calendarRef?.next()" />
        <div class="text-h6 ml-2">{{ title }}</div>
      </div>

      <v-btn-toggle v-model="viewType" mandatory density="comfortable" color="primary" variant="outlined" divided>
        <v-btn v-for="view in VIEW_TYPES" :key="view.value" :value="view.value" :prepend-icon="view.icon" size="small">
          {{ view.label() }}
        </v-btn>
      </v-btn-toggle>
    </div>

    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

    <v-sheet border rounded="lg" class="overflow-hidden" height="500">
      <v-calendar
        ref="calendarRef"
        v-model="focusDate"
        :type="viewType"
        :events="calendarEvents"
        :weekday-format="weekdayFormat"
        :month-format="monthFormat"
        :first-interval="FIRST_INTERVAL_HOUR"
        :interval-count="INTERVAL_COUNT"
        :interval-height="INTERVAL_HEIGHT"
        color="primary"
        @change="onRangeChange"
        @click:day="onDayClick"
        @click:date="onDayClick"
        @click:time="onDayClick"
        @click:event="onEventClick"
        >
        <template #day-body="{ date, week }">
          <div
            :class="{ first: date === week[0].date }"
            :style="{ top: nowY() }"
            class="v-current-time"
          ></div> 
        </template
      ></v-calendar>
    </v-sheet>
  </div>
</template>

<style>
.v-current-time {
  height: 2px;
  background-color: #ea4335;
  position: absolute;
  left: -1px;
  right: 0;
  pointer-events: none;

  &.first::before {
    content: '';
    position: absolute;
    background-color: #ea4335;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: -5px;
    margin-left: -6.5px;
  }
}

</style>