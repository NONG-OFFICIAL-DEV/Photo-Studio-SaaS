<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const props = defineProps({
  current: { type: Number, required: true },
  limit: { type: Number, default: null },
  resource: { type: String, required: true },
})

const { t } = useI18n()
const auth = useAuthStore()

const atLimit = computed(() => props.limit !== null && props.current >= props.limit)
const canManageBilling = computed(() => auth.hasPermission('tenant.billing.manage'))
</script>

<template>
  <v-alert v-if="atLimit" type="warning" variant="tonal" density="comfortable" class="mb-4">
    <div class="d-flex align-center justify-space-between flex-wrap ga-2">
      <span>{{ t('planLimits.reached', { resource, current, limit }) }}</span>
      <v-btn v-if="canManageBilling" size="small" variant="flat" color="warning" :to="{ name: 'billing' }">
        {{ t('planLimits.upgradePlan') }}
      </v-btn>
      <span v-else class="text-caption text-medium-emphasis">{{ t('planLimits.askOwner') }}</span>
    </div>
  </v-alert>
</template>
