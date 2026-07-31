<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import {
  getInventoryItemApi,
  recordInventoryMovementApi,
  deleteInventoryMovementApi,
} from '@/apis/inventory.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { formatDate } from '@/utils/dateFormat'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  itemId: { type: String, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const item = ref(null)
const loading = ref(false)
const actionLoading = ref(false)

const TYPE_ITEMS = computed(() => [
  { title: t('inventory.movementTypes.stockIn'), value: 'stock_in' },
  { title: t('inventory.movementTypes.stockOut'), value: 'stock_out' },
])

const movement = ref({ type: 'stock_in', quantity: null, reason: '', moved_at: null })
const movementError = ref('')

async function load() {
  if (!props.itemId) return
  loading.value = true
  try {
    const { data } = await getInventoryItemApi(props.itemId)
    item.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => [props.modelValue, props.itemId], async ([open]) => {
  if (open) {
    movement.value = { type: 'stock_in', quantity: null, reason: '', moved_at: null }
    movementError.value = ''
    await load()
  }
}, { immediate: true })

async function recordMovement() {
  movementError.value = ''
  if (!movement.value.quantity || Number(movement.value.quantity) <= 0) {
    movementError.value = t('inventory.errors.quantityRequired')
    return
  }

  actionLoading.value = true
  try {
    const { data } = await recordInventoryMovementApi(props.itemId, movement.value)
    item.value = data.data
    movement.value = { type: 'stock_in', quantity: null, reason: '', moved_at: null }
    emit('changed')
    appStore.notify(t('inventory.messages.movementRecorded'))
  } catch (error) {
    movementError.value = translateApiMessage(error, 'common.actionFailed')
  } finally {
    actionLoading.value = false
  }
}

async function removeMovement(movementId) {
  actionLoading.value = true
  try {
    const { data } = await deleteInventoryMovementApi(props.itemId, movementId)
    item.value = data.data
    emit('changed')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'common.actionFailed'), 'error')
  } finally {
    actionLoading.value = false
  }
}

const canAdjustStock = computed(() => auth.hasPermission('inventory.adjust-stock'))
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('inventory.itemDetails')" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="item">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <div class="text-h6">{{ item.name }}</div>
          <div class="text-body-2 text-medium-emphasis">{{ item.sku || '—' }} · {{ item.category || '—' }}</div>
        </div>
        <v-chip :color="item.is_low_stock ? 'error' : 'success'" variant="tonal">
          {{ item.quantity_on_hand }} {{ item.unit }}
        </v-chip>
      </div>

      <v-alert v-if="item.is_low_stock" type="warning" variant="tonal" density="compact" class="mb-4">
        {{ t('inventory.lowStockWarning', { threshold: item.reorder_threshold }) }}
      </v-alert>

      <div v-if="item.movements?.length" class="mb-4">
        <div class="text-subtitle-2 mb-2">{{ t('inventory.movements') }}</div>
        <v-table density="compact">
          <thead>
            <tr>
              <th>{{ t('inventory.movedAt') }}</th><th>{{ t('fields.type') }}</th><th>{{ t('fields.quantity') }}</th><th>{{ t('fields.reason') }}</th><th style="width: 40px" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in item.movements" :key="m.id">
              <td>{{ formatDate(m.moved_at) }}</td>
              <td>
                <v-chip :color="m.type === 'stock_in' ? 'success' : 'error'" size="small" variant="tonal">
                  {{ t(`inventory.movementTypes.${m.type === 'stock_in' ? 'stockIn' : 'stockOut'}`) }}
                </v-chip>
              </td>
              <td>{{ m.quantity }}</td>
              <td>{{ m.reason || '—' }}</td>
              <td>
                <v-btn v-if="canAdjustStock" icon="mdi-close" size="small" variant="text" :loading="actionLoading" @click="removeMovement(m.id)" />
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>

      <div v-if="canAdjustStock" class="mb-2">
        <div class="text-subtitle-2 mb-2">{{ t('inventory.recordMovement') }}</div>
        <v-alert v-if="movementError" type="error" variant="tonal" density="compact" class="mb-2">{{ movementError }}</v-alert>
        <v-row dense>
          <v-col cols="6" sm="3">
            <v-select v-model="movement.type" :label="t('fields.type')" :items="TYPE_ITEMS" density="compact" hide-details />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field v-model.number="movement.quantity" :label="t('fields.quantity')" type="number" step="0.01" density="compact" hide-details />
          </v-col>
          <v-col cols="6" sm="3">
            <AppDatePicker v-model="movement.moved_at" :label="t('inventory.movedAt')" />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field v-model="movement.reason" :label="t('fields.reason')" density="compact" hide-details />
          </v-col>
        </v-row>
        <v-btn class="mt-2" color="primary" variant="tonal" :loading="actionLoading" @click="recordMovement">
          {{ t('inventory.recordMovement') }}
        </v-btn>
      </div>
    </div>
  </AppDialog>
</template>
