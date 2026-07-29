<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: null },
  message: { type: String, default: null },
  loading: { type: Boolean, default: false },
  color: { type: String, default: 'error' },
  confirmText: { type: String, default: null },
  cancelText: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const resolvedTitle = computed(() => props.title ?? t('common.confirm'))
const resolvedMessage = computed(() => props.message ?? t('common.confirmMessage'))
const resolvedConfirmText = computed(() => props.confirmText ?? t('common.confirm'))
const resolvedCancelText = computed(() => props.cancelText ?? t('common.cancel'))
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="420" @update:model-value="emit('update:modelValue', $event)">
    <v-card>
      <v-card-title class="text-h6">{{ resolvedTitle }}</v-card-title>
      <v-card-text>{{ resolvedMessage }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="loading" @click="emit('cancel'); emit('update:modelValue', false)">
          {{ resolvedCancelText }}
        </v-btn>
        <v-btn :color="color" variant="flat" :loading="loading" @click="emit('confirm')">
          {{ resolvedConfirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
