<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { orderSchema } from '@/utils/validators'
import { createOrderApi } from '@/apis/order.api'
import { getCustomersApi } from '@/apis/customer.api'
import { getBookingsApi } from '@/apis/booking.api'
import { getServicesApi } from '@/apis/service.api'
import { getServiceAddOnsApi } from '@/apis/service-addon.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')

const customerOptions = ref([])
const customerSearchLoading = ref(false)
const bookingOptions = ref([])
const catalogOptions = ref([])

const items = ref([])
const catalogPick = ref(null)

const bookingSelectItems = computed(() => bookingOptions.value.map(booking => ({
  id: booking.id,
  label: `${new Date(booking.starts_at).toLocaleDateString()} — ${booking.title || booking.type}`,
})))

async function loadCatalog() {
  const [servicesRes, addonsRes] = await Promise.all([
    getServicesApi({ is_active: '1', perPage: 100 }),
    getServiceAddOnsApi(),
  ])

  catalogOptions.value = [
    ...servicesRes.data.data.map(s => ({ key: `service:${s.id}`, type: 'service', id: s.id, name: s.name, price: s.price })),
    ...addonsRes.data.data.filter(a => a.is_active).map(a => ({ key: `addon:${a.id}`, type: 'addon', id: a.id, name: a.name, price: a.price })),
  ]
}

watch(() => props.modelValue, (open) => {
  if (open) {
    items.value = []
    catalogPick.value = null
    errorMessage.value = ''
    loadCatalog()
    loadInitialCustomers()
  }
})

/**
 * The autocomplete only searches on typed input (@update:search) — left
 * on its own, that means an empty dropdown with no explanation the first
 * time the dialog opens, since nothing has been typed yet. Pre-populate
 * with a first page of customers so there's always something to see and
 * pick from immediately; typing still narrows it via searchCustomers().
 */
async function loadInitialCustomers() {
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function searchCustomers(term) {
  if (!term) return loadInitialCustomers()
  if (term.length < 2) return

  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ search: term, perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function loadBookingsForCustomer(customerId, setFieldValue) {
  bookingOptions.value = []
  setFieldValue('booking_id', null)
  if (!customerId) return

  const { data } = await getBookingsApi({ customer_id: customerId, perPage: 20 })
  bookingOptions.value = data.data
}

function addCatalogItem() {
  if (!catalogPick.value) return
  const picked = catalogOptions.value.find(c => c.key === catalogPick.value)
  if (!picked) return

  items.value.push({
    service_id: picked.type === 'service' ? picked.id : null,
    addon_id: picked.type === 'addon' ? picked.id : null,
    name: picked.name,
    unit_price: picked.price,
    quantity: 1,
    readonly: true,
  })
  catalogPick.value = null
}

function addCustomItem() {
  items.value.push({ service_id: null, addon_id: null, name: '', unit_price: null, quantity: 1, readonly: false })
}

function removeItem(index) {
  items.value.splice(index, 1)
}

function lineTotal(item) {
  return (Number(item.unit_price) || 0) * (Number(item.quantity) || 0)
}

function computeSubtotal() {
  return items.value.reduce((sum, item) => sum + lineTotal(item), 0)
}

async function onSubmit(values) {
  errorMessage.value = ''

  if (!items.value.length) {
    errorMessage.value = t('orders.errors.needsOneItem')
    return
  }

  const invalidCustom = items.value.some(item => !item.readonly && (!item.name || item.unit_price === null || item.unit_price === ''))
  if (invalidCustom) {
    errorMessage.value = t('orders.errors.customItemIncomplete')
    return
  }

  loading.value = true
  try {
    await createOrderApi({
      ...values,
      items: items.value.map(item => ({
        service_id: item.service_id,
        addon_id: item.addon_id,
        name: item.name,
        unit_price: item.unit_price,
        quantity: item.quantity,
      })),
    })
    appStore.notify(t('orders.orderCreated'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('orders.errors.saveFailed')
  } finally {
    loading.value = false
  }
}

const subtotal = computed(() => computeSubtotal())
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('orders.newOrder')" max-width="720" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="orderSchema" :initial-values="{ customer_id: null, booking_id: null, discount_amount: 0, notes: '' }" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-autocomplete
              :model-value="values.customer_id"
              :label="`${t('fields.customer')} *`"
              item-title="name"
              item-value="id"
              :items="customerOptions"
              :loading="customerSearchLoading"
              :error-messages="errors.customer_id"
              no-filter
              @update:search="searchCustomers"
              @update:model-value="(val) => { setFieldValue('customer_id', val); loadBookingsForCustomer(val, setFieldValue) }"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps" :subtitle="item.raw.phone" />
              </template>
            </v-autocomplete>
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.booking_id"
              :label="t('orders.linkedBooking')"
              clearable
              :disabled="!bookingOptions.length"
              item-title="label"
              item-value="id"
              :items="bookingSelectItems"
              @update:model-value="setFieldValue('booking_id', $event)"
            />
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">{{ t('orders.items') }}</div>

        <div class="d-flex ga-2 mb-3">
          <v-select
            v-model="catalogPick"
            :label="t('orders.addFromCatalog')"
            density="compact"
            hide-details
            item-title="name"
            item-value="key"
            :items="catalogOptions"
          >
            <template #item="{ props: itemProps, item }">
              <v-list-item v-bind="itemProps" :subtitle="`$${item.raw.price} · ${item.raw.type}`" />
            </template>
          </v-select>
          <v-btn icon="mdi-plus" variant="tonal" @click="addCatalogItem" />
          <v-btn variant="outlined" prepend-icon="mdi-pencil-plus-outline" @click="addCustomItem">{{ t('orders.customItem') }}</v-btn>
        </div>

        <v-table density="compact" class="mb-4">
          <thead>
            <tr>
              <th>{{ t('fields.name') }}</th>
              <th style="width: 120px">{{ t('fields.unitPrice') }}</th>
              <th style="width: 90px">{{ t('fields.quantity') }}</th>
              <th style="width: 100px">{{ t('fields.total') }}</th>
              <th style="width: 40px" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in items" :key="index">
              <td>
                <span v-if="item.readonly">{{ item.name }}</span>
                <v-text-field v-else v-model="item.name" density="compact" hide-details :placeholder="t('orders.itemNamePlaceholder')" />
              </td>
              <td>
                <span v-if="item.readonly">${{ item.unit_price }}</span>
                <v-text-field v-else v-model.number="item.unit_price" type="number" step="0.01" density="compact" hide-details />
              </td>
              <td>
                <v-text-field v-model.number="item.quantity" type="number" min="1" density="compact" hide-details />
              </td>
              <td>${{ lineTotal(item).toFixed(2) }}</td>
              <td>
                <v-btn icon="mdi-close" size="small" variant="text" @click="removeItem(index)" />
              </td>
            </tr>
            <tr v-if="!items.length">
              <td colspan="5" class="text-center text-medium-emphasis py-4">{{ t('orders.noItemsYet') }}</td>
            </tr>
          </tbody>
        </v-table>

        <v-row>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="notes">
              <v-textarea v-bind="field" :label="t('fields.notes')" rows="2" :error-messages="errors.notes" />
            </Field>
          </v-col>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="discount_amount">
              <v-text-field v-bind="field" :label="t('fields.discount')" type="number" step="0.01" prefix="$" :error-messages="errors.discount_amount" />
            </Field>
            <div class="text-body-2 d-flex justify-space-between">
              <span>{{ t('fields.subtotal') }}</span><span>${{ subtotal.toFixed(2) }}</span>
            </div>
            <div class="text-h6 d-flex justify-space-between">
              <span>{{ t('fields.total') }}</span><span>${{ Math.max(0, subtotal - (Number(values.discount_amount) || 0)).toFixed(2) }}</span>
            </div>
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('orders.createOrder') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
