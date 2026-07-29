<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  entry: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const formatted = computed(() => (props.entry?.properties ? JSON.stringify(props.entry.properties, null, 2) : '{}'))
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('auditPage.details')" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <p class="text-body-2 text-medium-emphasis mb-2">{{ entry?.description }}</p>
    <pre class="details-json pa-3 rounded-lg">{{ formatted }}</pre>
  </AppDialog>
</template>

<style scoped>
.details-json {
  background: rgba(var(--v-theme-on-surface), 0.05);
  overflow-x: auto;
  font-size: 0.8125rem;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
