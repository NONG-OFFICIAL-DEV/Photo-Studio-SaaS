<script setup>
import { computed } from 'vue'

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

const DEFAULT_MAP = {
  trial: { color: 'info', label: 'Trial' },
  active: { color: 'success', label: 'Active' },
  expired: { color: 'error', label: 'Expired' },
  suspended: { color: 'warning', label: 'Suspended' },
  cancelled: { color: 'grey', label: 'Cancelled' },
  pending: { color: 'warning', label: 'Pending' },
  confirmed: { color: 'info', label: 'Confirmed' },
  in_progress: { color: 'info', label: 'In Progress' },
  completed: { color: 'success', label: 'Completed' },
  delivered: { color: 'success', label: 'Delivered' },
  inactive: { color: 'grey', label: 'Inactive' },
  locked: { color: 'error', label: 'Locked' },
}

const resolved = computed(() => {
  const merged = { ...DEFAULT_MAP, ...props.map }
  return merged[props.status] ?? { color: 'grey', label: props.status }
})
</script>

<template>
  <v-chip :color="resolved.color" :size="size" label variant="tonal">
    {{ resolved.label }}
  </v-chip>
</template>
