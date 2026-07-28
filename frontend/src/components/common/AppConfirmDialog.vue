<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Confirm' },
  message: { type: String, default: 'Are you sure?' },
  loading: { type: Boolean, default: false },
  color: { type: String, default: 'error' },
  confirmText: { type: String, default: 'Confirm' },
  cancelText: { type: String, default: 'Cancel' },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="420" @update:model-value="emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="text-h6">{{ title }}</v-card-title>
      <v-card-text>{{ message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="loading" @click="emit('cancel'); emit('update:modelValue', false)">
          {{ cancelText }}
        </v-btn>
        <v-btn :color="color" variant="flat" :loading="loading" @click="emit('confirm')">
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
