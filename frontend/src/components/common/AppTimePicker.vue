<script setup>
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: null },
  label: { type: String, default: '' },
  clearable: { type: Boolean, default: false },
  density: { type: String, default: 'comfortable' },
  errorMessages: { type: [String, Array], default: () => [] },
  hint: { type: String, default: '' },
  persistentHint: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const menu = ref(false)
const hourListRef = ref(null)
const minuteListRef = ref(null)
const periodListRef = ref(null)

const HOURS = Array.from({ length: 12 }, (_, i) => i + 1)
const MINUTES = Array.from({ length: 60 }, (_, i) => i)
const PERIODS = ['AM', 'PM']

function parse(time24) {
  if (!time24) return { hour: 9, minute: 0, period: 'AM' }
  const [h, m] = time24.split(':').map(Number)
  return {
    hour: h % 12 === 0 ? 12 : h % 12,
    minute: m,
    period: h < 12 ? 'AM' : 'PM',
  }
}

const selected = ref(parse(props.modelValue))

watch(() => props.modelValue, (val) => {
  selected.value = parse(val)
})

function to24Hour({ hour, minute, period }) {
  let h = hour % 12
  if (period === 'PM') h += 12
  return `${String(h).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
}

const displayText = computed(() => {
  if (!props.modelValue) return ''
  const { hour, minute, period } = selected.value
  return `${hour}:${String(minute).padStart(2, '0')} ${period}`
})

function selectHour(hour) {
  selected.value = { ...selected.value, hour }
  emit('update:modelValue', to24Hour(selected.value))
}

function selectMinute(minute) {
  selected.value = { ...selected.value, minute }
  emit('update:modelValue', to24Hour(selected.value))
}

function selectPeriod(period) {
  selected.value = { ...selected.value, period }
  emit('update:modelValue', to24Hour(selected.value))
}

function scrollActiveIntoView(listRef) {
  const activeEl = listRef.value?.$el?.querySelector('.v-list-item--active')
  activeEl?.scrollIntoView({ block: 'center' })
}

function onOpen(isOpen) {
  if (!isOpen) return
  nextTick(() => {
    scrollActiveIntoView(hourListRef)
    scrollActiveIntoView(minuteListRef)
    scrollActiveIntoView(periodListRef)
  })
}
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" location="bottom start" width="auto" min-width="100" @update:model-value="onOpen">
    <template #activator="{ props: activatorProps }">
      <v-text-field
        v-bind="activatorProps"
        :model-value="displayText"
        :label="label"
        :clearable="clearable"
        :error-messages="errorMessages"
        :density="density"
        :hint="hint"
        :persistent-hint="persistentHint"
        prepend-inner-icon="mdi-clock-outline"
        readonly
        @click:clear="emit('update:modelValue', null)"
      />
    </template>
    <v-sheet class="d-flex" max-height="280">
      <v-list ref="hourListRef" density="compact" class="overflow-y-auto" style="min-width: 64px">
        <v-list-item
          v-for="hour in HOURS"
          :key="hour"
          :title="String(hour)"
          :active="hour === selected.hour"
          @click="selectHour(hour)"
        />
      </v-list>
      <v-divider vertical />
      <v-list ref="minuteListRef" density="compact" class="overflow-y-auto" style="min-width: 64px">
        <v-list-item
          v-for="minute in MINUTES"
          :key="minute"
          :title="String(minute).padStart(2, '0')"
          :active="minute === selected.minute"
          @click="selectMinute(minute)"
        />
      </v-list>
      <v-divider vertical />
      <v-list ref="periodListRef" density="compact" class="overflow-y-auto" style="min-width: 64px">
        <v-list-item
          v-for="period in PERIODS"
          :key="period"
          :title="period"
          :active="period === selected.period"
          @click="selectPeriod(period)"
        />
      </v-list>
    </v-sheet>
  </v-menu>
</template>
