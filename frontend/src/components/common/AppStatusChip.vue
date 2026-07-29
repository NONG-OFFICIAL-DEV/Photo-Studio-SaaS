<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/*
 * Generic status badge reused across every module (bookings, orders,
 * invoices, subscriptions, ...). Pass `map` to override/extend the
 * default color/label per status for a given domain.
 */
const props = defineProps({
  status: { type: String, required: true },
  map: { type: Object, default: () => ({}) },
  size: { type: String, default: 'default' },
})

const { t } = useI18n()

const DEFAULT_MAP = computed(() => ({
  trial: { color: 'info', label: t('common.status.trial') },
  active: { color: 'success', label: t('common.status.active') },
  expired: { color: 'error', label: t('common.status.expired') },
  suspended: { color: 'warning', label: t('common.status.suspended') },
  cancelled: { color: 'grey', label: t('common.status.cancelled') },
  pending: { color: 'warning', label: t('common.status.pending') },
  confirmed: { color: 'info', label: t('common.status.confirmed') },
  in_progress: { color: 'info', label: t('common.status.inProgress') },
  completed: { color: 'success', label: t('common.status.completed') },
  delivered: { color: 'success', label: t('common.status.delivered') },
  inactive: { color: 'grey', label: t('common.status.inactive') },
  locked: { color: 'error', label: t('common.status.locked') },
}))

const resolved = computed(() => {
  const merged = { ...DEFAULT_MAP.value, ...props.map }
  return merged[props.status] ?? { color: 'grey', label: props.status }
})
</script>

<template>
  <v-chip :color="resolved.color" :size="size" label variant="tonal">
    {{ resolved.label }}
  </v-chip>
</template>
