<script setup>
import { computed, ref } from 'vue'
import { format, parseISO } from 'date-fns'

const props = defineProps({
  modelValue: { type: [String, Date, null], default: null },
  label: { type: String, default: '' },
  displayFormat: { type: String, default: 'dd/MM/yyyy' },
  clearable: { type: Boolean, default: true },
  density: { type: String, default: 'comfortable' },
  errorMessages: { type: [String, Array], default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const menu = ref(false)

const dateValue = computed({
  get() {
    if (!props.modelValue) return null
    return typeof props.modelValue === 'string' ? parseISO(props.modelValue) : props.modelValue
  },
  set(val) {
    // toISOString() converts to UTC, which rolls back to the previous day
    // for any timezone ahead of UTC (the picker gives local midnight for
    // the clicked day) — format() reads the Date's local components instead,
    // matching how parseISO() above already reads the string back as local.
    emit('update:modelValue', val ? format(val, 'yyyy-MM-dd') : null)
    menu.value = false
  },
})

const displayText = computed(() => (dateValue.value ? format(dateValue.value, props.displayFormat) : ''))
</script>

<template>
  <v-menu v-model="menu" :close-on-content-click="false" location="bottom start">
    <template #activator="{ props: activatorProps }">
      <v-text-field
        v-bind="activatorProps"
        :model-value="displayText"
        :label="label"
        :clearable="clearable"
        :error-messages="errorMessages"
        :density="density"
        prepend-inner-icon="mdi-calendar"
        readonly
        @click:clear="emit('update:modelValue', null)"
      />
    </template>
    <v-date-picker v-model="dateValue" show-adjacent-months hide-header color="primary"/>
  </v-menu>
</template>
