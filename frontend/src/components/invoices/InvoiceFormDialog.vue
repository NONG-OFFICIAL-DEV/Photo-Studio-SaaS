<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { invoiceSchema } from '@/utils/validators'
import { createInvoiceApi } from '@/apis/invoice.api'
import { getCustomersApi } from '@/apis/customer.api'
import { getOrdersApi, getOrderApi } from '@/apis/order.api'
import { getServicesApi } from '@/apis/service.api'
import { getServiceAddOnsApi } from '@/apis/service-addon.api'
import { getPackagesApi } from '@/apis/package.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { formatCurrency } from '@/utils/currencyFormat'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  // Full order object (with nested customer/items) when opened via an
  // order's "Create Invoice" action — seeds order_id/customer_id directly
  // and skips the order picker, since the order is already known.
  presetOrder: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const fromOrder = ref(false)
const customerOptions = ref([])
const customerSearchLoading = ref(false)
const orderOptions = ref([])
const orderSearchLoading = ref(false)
const orderPreview = ref(null)
const catalogOptions = ref([])

const items = ref([])
const catalogPick = ref(null)

const orderSelectItems = computed(() =>
  orderOptions.value.map((order) => ({
    id: order.id,
    title: `${order.customer?.name ?? ''} — $${order.total}`,
    total: order.total,
  })),
)

const initialValues = computed(() => ({
  customer_id: props.presetOrder?.customer?.id ?? null,
  order_id: props.presetOrder?.id ?? null,
  issue_date: null,
  due_date: null,
  discount_amount: 0,
  tax_rate: 0,
  notes: '',
}))

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      fromOrder.value = Boolean(props.presetOrder)
      items.value = []
      catalogPick.value = null
      orderPreview.value = props.presetOrder ?? null
      errorMessage.value = ''
      loadCatalog()

      if (props.presetOrder) {
        orderOptions.value = [props.presetOrder]
      } else {
        loadInitialCustomers()
        loadInitialOrders()
      }
    }
  },
)

async function loadCatalog() {
  const [servicesRes, addonsRes, packagesRes] = await Promise.all([
    getServicesApi({ is_active: '1', perPage: 100 }),
    getServiceAddOnsApi(),
    getPackagesApi({ is_active: '1', perPage: 100 }),
  ])

  catalogOptions.value = [
    ...servicesRes.data.data.map((s) => ({
      key: `service:${s.id}`,
      type: 'service',
      id: s.id,
      name: s.name,
      price: s.price,
    })),
    ...addonsRes.data.data
      .filter((a) => a.is_active)
      .map((a) => ({ key: `addon:${a.id}`, type: 'addon', id: a.id, name: a.name, price: a.price })),
    ...packagesRes.data.data.map((p) => ({
      key: `package:${p.id}`,
      type: 'package',
      id: p.id,
      name: p.name,
      price: p.final_price,
      optionalComponents: (p.components ?? []).filter((c) => c.is_optional),
    })),
  ]
}

/**
 * A package's optional add-ons (defined in the Package Builder) become
 * available as checkboxes once that package is added as a line — checking
 * one appends it as its own normal addon/service line, unchecking removes
 * that same line. Deduped across multiple selected packages by catalog ref.
 */
const availableOptionalAddons = computed(() => {
  const selectedPackageIds = items.value.filter((item) => item.package_id).map((item) => item.package_id)
  const seen = new Map()

  for (const pkg of catalogOptions.value.filter((c) => c.type === 'package' && selectedPackageIds.includes(c.id))) {
    for (const component of pkg.optionalComponents) {
      const key = component.service_id ? `service:${component.service_id}` : `addon:${component.addon_id}`
      if (!seen.has(key)) seen.set(key, component)
    }
  }

  return [...seen.values()]
})

function isOptionalAddonSelected(component) {
  return items.value.some(
    (item) =>
      (component.service_id && item.service_id === component.service_id) ||
      (component.addon_id && item.addon_id === component.addon_id),
  )
}

function toggleOptionalAddon(component, checked) {
  if (checked) {
    items.value.push({
      service_id: component.service_id,
      addon_id: component.addon_id,
      package_id: null,
      name: component.name,
      unit_price: component.unit_price,
      quantity: 1,
      readonly: true,
    })
    return
  }

  const index = items.value.findIndex(
    (item) =>
      (component.service_id && item.service_id === component.service_id) ||
      (component.addon_id && item.addon_id === component.addon_id),
  )
  if (index !== -1) items.value.splice(index, 1)
}

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

async function loadInitialOrders() {
  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ perPage: 20 })
    orderOptions.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

async function searchOrders(term) {
  if (!term) return loadInitialOrders()
  if (term.length < 2) return

  orderSearchLoading.value = true
  try {
    const { data } = await getOrdersApi({ search: term, perPage: 20 })
    orderOptions.value = data.data
  } finally {
    orderSearchLoading.value = false
  }
}

async function selectOrder(orderId, setFieldValue) {
  setFieldValue('order_id', orderId)
  orderPreview.value = null

  if (!orderId) {
    setFieldValue('customer_id', null)
    return
  }

  const { data } = await getOrderApi(orderId)
  orderPreview.value = data.data
  setFieldValue('customer_id', data.data.customer?.id ?? null)
}

function toggleFromOrder(value, setFieldValue) {
  fromOrder.value = value
  orderPreview.value = null
  setFieldValue('order_id', null)
  setFieldValue('customer_id', null)
}

function addCatalogItem() {
  if (!catalogPick.value) return
  const picked = catalogOptions.value.find((c) => c.key === catalogPick.value)
  if (!picked) return

  items.value.push({
    service_id: picked.type === 'service' ? picked.id : null,
    addon_id: picked.type === 'addon' ? picked.id : null,
    package_id: picked.type === 'package' ? picked.id : null,
    name: picked.name,
    unit_price: picked.price,
    quantity: 1,
    readonly: true,
  })
  catalogPick.value = null
}

function addCustomItem() {
  items.value.push({
    service_id: null,
    addon_id: null,
    package_id: null,
    name: '',
    unit_price: null,
    quantity: 1,
    readonly: false,
  })
}

function removeItem(index) {
  items.value.splice(index, 1)
}

function lineTotal(item) {
  return (Number(item.unit_price) || 0) * (Number(item.quantity) || 0)
}

const subtotal = computed(() => {
  if (fromOrder.value) return Number(orderPreview.value?.subtotal) || 0
  return items.value.reduce((sum, item) => sum + lineTotal(item), 0)
})

function totals(values) {
  const discount = Number(values.discount_amount) || 0
  const taxRate = Number(values.tax_rate) || 0
  const taxable = Math.max(0, subtotal.value - discount)
  const taxAmount = Math.round(taxable * (taxRate / 100) * 100) / 100
  return { taxable, taxAmount, total: taxable + taxAmount }
}

async function onSubmit(values) {
  errorMessage.value = ''

  if (!fromOrder.value) {
    if (!items.value.length) {
      errorMessage.value = t('invoices.errors.needsOneItem')
      return
    }

    const invalidCustom = items.value.some(
      (item) => !item.readonly && (!item.name || item.unit_price === null || item.unit_price === ''),
    )
    if (invalidCustom) {
      errorMessage.value = t('invoices.errors.customItemIncomplete')
      return
    }
  }

  loading.value = true
  try {
    const payload = { ...values }

    if (!fromOrder.value) {
      payload.items = items.value.map((item) => ({
        service_id: item.service_id,
        addon_id: item.addon_id,
        package_id: item.package_id,
        name: item.name,
        unit_price: item.unit_price,
        quantity: item.quantity,
      }))
    }

    await createInvoiceApi(payload)
    appStore.notify(t('invoices.messages.createdSuccess'))
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'invoices.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('invoices.newInvoice')"
    max-width="760"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="invoiceSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-alert v-if="presetOrder" type="info" variant="tonal" density="compact" class="mb-4">
          {{ t('invoices.creatingFromOrder', { name: presetOrder.customer?.name }) }}
        </v-alert>

        <!-- v-if="!presetOrder" -->
        <v-switch
          :model-value="fromOrder"
          :label="t('invoices.createFromOrder')"
          color="primary"
          hide-details
          class="mb-3"
          @update:model-value="toggleFromOrder($event, setFieldValue)"
        />

        <v-row>
          <v-col v-if="fromOrder" cols="12" sm="4">
            <v-autocomplete
              :model-value="values.order_id"
              :label="`${t('invoices.linkedOrder')} *`"
              item-title="title"
              item-value="id"
              :items="orderSelectItems"
              :loading="orderSearchLoading"
              :error-messages="errors.order_id"
              :disabled="Boolean(presetOrder)"
              no-filter
              @update:search="searchOrders"
              @update:model-value="selectOrder($event, setFieldValue)"
            />
          </v-col>
          <v-col v-else cols="12" sm="4">
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
              @update:model-value="setFieldValue('customer_id', $event)"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps" :subtitle="item.raw.phone" />
              </template>
            </v-autocomplete>
          </v-col>

          <v-col cols="12" sm="4">
            <AppDatePicker
              :model-value="values.issue_date"
              :label="t('invoices.issueDate')"
              @update:model-value="setFieldValue('issue_date', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <AppDatePicker
              :model-value="values.due_date"
              :label="t('invoices.dueDate')"
              @update:model-value="setFieldValue('due_date', $event)"
            />
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <template v-if="fromOrder">
          <div class="text-subtitle-2 mb-2">{{ t('invoices.itemsFromOrder') }}</div>
          <v-table v-if="orderPreview" density="compact" class="mb-4">
            <thead>
              <tr>
                <th>{{ t('fields.name') }}</th>
                <th>{{ t('fields.unitPrice') }}</th>
                <th>{{ t('fields.quantity') }}</th>
                <th>{{ t('fields.total') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="orderItem in orderPreview.items" :key="orderItem.id">
                <td>{{ orderItem.name }}</td>
                <td>${{ orderItem.unit_price }}</td>
                <td>{{ orderItem.quantity }}</td>
                <td>${{ orderItem.line_total }}</td>
              </tr>
            </tbody>
          </v-table>
          <div v-else class="text-body-2 text-medium-emphasis mb-4">{{ t('invoices.selectOrderPrompt') }}</div>
        </template>

        <template v-else>
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
                <v-list-item v-bind="itemProps">
                  <template #append>
                    <v-icon
                      :icon="item.raw.type === 'service' ? 'mdi-tag-multiple-outline' : 'mdi-plus-box-multiple-outline'"
                      size="16"
                      class="me-1"
                    />
                    <span class="text-body-2 text-medium-emphasis">
                      {{ `$${item.raw.price}` }} {{ $t(`services.itemType.${item.raw.type}`) }}
                    </span>
                  </template>
                </v-list-item>
              </template>
            </v-select>
            <v-btn icon="mdi-plus" variant="tonal" @click="addCatalogItem" />
            <v-btn variant="outlined" prepend-icon="mdi-pencil-plus-outline" @click="addCustomItem">{{
              t('orders.customItem')
            }}</v-btn>
          </div>

          <v-table class="mb-4">
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
                  <v-text-field
                    v-else
                    v-model="item.name"
                    density="compact"
                    hide-details
                    :placeholder="t('orders.itemNamePlaceholder')"
                  />
                </td>
                <td>
                  <span v-if="item.readonly">${{ item.unit_price }}</span>
                  <v-text-field
                    v-else
                    v-model.number="item.unit_price"
                    type="number"
                    step="0.01"
                    density="compact"
                    hide-details
                  />
                </td>
                <td>
                  <v-text-field v-model.number="item.quantity" type="number" min="1" density="compact" hide-details />
                </td>
                <td>{{ formatCurrency(lineTotal(item)) }}</td>
                <td>
                  <v-btn icon="mdi-close" size="x-small" class="bg-error" variant="text" @click="removeItem(index)" />
                </td>
              </tr>
              <tr v-if="!items.length">
                <td colspan="5" class="text-center text-medium-emphasis py-4">{{ t('orders.noItemsYet') }}</td>
              </tr>
            </tbody>
          </v-table>

          <div v-if="availableOptionalAddons.length" class="mb-4">
            <div class="text-body-2 text-medium-emphasis mb-1">{{ t('packages.optionalAddonsAvailable') }}</div>
            <v-checkbox
              v-for="component in availableOptionalAddons"
              :key="component.service_id || component.addon_id"
              :model-value="isOptionalAddonSelected(component)"
              :label="`${component.name} (+${formatCurrency(component.unit_price)})`"
              density="compact"
              hide-details
              @update:model-value="toggleOptionalAddon(component, $event)"
            />
          </div>
        </template>

        <v-row>
          <v-col cols="12" sm="6">
            <v-textarea
              :model-value="values.notes"
              :label="t('fields.notes')"
              rows="2"
              :error-messages="errors.notes"
              @update:model-value="setFieldValue('notes', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <div class="d-flex ga-2">
              <v-text-field
                :model-value="values.discount_amount"
                :label="t('fields.discount')"
                type="number"
                step="0.01"
                prefix="$"
                :error-messages="errors.discount_amount"
                @update:model-value="setFieldValue('discount_amount', $event)"
              />
              <v-text-field
                :model-value="values.tax_rate"
                :label="t('invoices.taxRate')"
                type="number"
                step="0.01"
                suffix="%"
                :error-messages="errors.tax_rate"
                @update:model-value="setFieldValue('tax_rate', $event)"
              />
            </div>
            <div class="text-body-2 d-flex justify-space-between">
              <span>{{ t('fields.subtotal') }}</span
              ><span>{{ formatCurrency(subtotal) }}</span>
            </div>
            <div class="text-body-2 d-flex justify-space-between">
              <span>{{ t('invoices.taxAmount') }}</span
              ><span>{{ formatCurrency(totals(values).taxAmount) }}</span>
            </div>
            <div class="text-h6 d-flex justify-space-between">
              <span>{{ t('fields.total') }}</span
              ><span>{{ formatCurrency(totals(values).total) }}</span>
            </div>
          </v-col>
        </v-row>
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{
        t('common.cancel')
      }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{
        t('invoices.createInvoice')
      }}</v-btn>
    </template>
  </AppDialog>
</template>
