<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="d-flex flex-wrap align-center justify-space-between mb-4 ga-3">
    <div>
      <h1 class="text-h5 font-weight-bold">{{ title }}</h1>
      <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-0">{{ subtitle }}</p>
    </div>
    <div class="d-flex align-center ga-2">
      <slot name="actions" />
    </div>
  </div>
</template>
