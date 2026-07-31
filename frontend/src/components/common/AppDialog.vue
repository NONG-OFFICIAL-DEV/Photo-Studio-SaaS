<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, required: true },
  maxWidth: { type: [String, Number], default: 600 },
  persistent: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidth"
    :persistent="persistent"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <span class="text-h6">{{ title }}</span>
        <v-btn icon="mdi-close" variant="text" density="comfortable" @click="emit('update:modelValue', false)" />
      </v-card-title>

      <v-divider />

      <v-card-text class="py-4">
        <slot />
      </v-card-text>

      <v-divider v-if="$slots.actions" />

      <v-card-actions v-if="$slots.actions" class="pa-4 ga-2 justify-end flex-wrap">
        <slot name="actions" :loading="loading" />
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
