<script setup>
import { computed, ref } from 'vue'
import { format, parseISO } from 'date-fns'

const props = defineProps({
  modelValue: { type: [String, Date, null], default: null },
  label: { type: String, default: '' },
  displayFormat: { type: String, default: 'MMM d, yyyy' },
  clearable: { type: Boolean, default: true },
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
    emit('update:modelValue', val ? val.toISOString().slice(0, 10) : null)
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
        prepend-inner-icon="mdi-calendar"
        readonly
        @click:clear="emit('update:modelValue', null)"
      />
    </template>
    <v-date-picker v-model="dateValue" show-adjacent-months />
  </v-menu>
</template>
