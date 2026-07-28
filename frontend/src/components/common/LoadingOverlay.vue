<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
})
</script>

<template>
  <v-overlay
    :model-value="modelValue"
    persistent
    class="align-center justify-center"
    scrim="#000"
  >
    <v-progress-circular indeterminate color="primary" size="56" />
  </v-overlay>
</template>
