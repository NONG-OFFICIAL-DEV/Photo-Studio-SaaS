<script setup>
defineProps({
  icon: { type: String, default: 'mdi-image-off-outline' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="d-flex flex-column align-center justify-center text-center py-12 px-4">
    <v-icon :icon="icon" size="64" color="grey-lighten-1" class="mb-4" />
    <div class="text-h6 text-medium-emphasis mb-1">{{ title }}</div>
    <div v-if="description" class="text-body-2 text-medium-emphasis mb-4">{{ description }}</div>
    <slot name="action" />
  </div>
</template>
