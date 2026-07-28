<script setup>
import { ref, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search...' },
  debounce: { type: Number, default: 350 },
})

const emit = defineEmits(['update:modelValue'])

const value = ref(props.modelValue)

const emitDebounced = useDebounceFn((val) => emit('update:modelValue', val), props.debounce)

watch(value, (val) => emitDebounced(val))
watch(() => props.modelValue, (val) => {
  if (val !== value.value) value.value = val
})
</script>

<template>
  <v-text-field
    v-model="value"
    :placeholder="placeholder"
    prepend-inner-icon="mdi-magnify"
    clearable
    hide-details
    density="comfortable"
    variant="outlined"
    class="app-search"
  />
</template>

<style scoped>
.app-search {
  max-width: 320px;
}
</style>
