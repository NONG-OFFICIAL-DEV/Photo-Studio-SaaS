<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { packageSchema } from '@/utils/validators'
import { createPackageApi, updatePackageApi } from '@/apis/package.api'
import { getServicesApi } from '@/apis/service.api'
import { getServiceAddOnsApi } from '@/apis/service-addon.api'
import { translateApiMessage } from '@/utils/apiMessages'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  pkg: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const catalogOptions = ref([])
const components = ref([])
const catalogPick = ref(null)

const isEdit = computed(() => Boolean(props.pkg?.id))
const title = computed(() => (isEdit.value ? t('packages.editPackage') : t('packages.newPackage')))

const DISCOUNT_TYPES = computed(() => [
  { title: t('packages.discountTypes.none'), value: null },
  { title: t('packages.discountTypes.percent'), value: 'percent' },
  { title: t('packages.discountTypes.fixed'), value: 'fixed' },
])

const initialValues = computed(() => ({
  name: props.pkg?.name ?? '',
  description: props.pkg?.description ?? '',
  discount_type: props.pkg?.discount_type ?? null,
  discount_value: props.pkg?.discount_value ?? null,
  override_price: props.pkg?.override_price ?? null,
  is_active: props.pkg?.is_active ?? true,
}))

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    catalogPick.value = null
    components.value = (props.pkg?.components ?? []).map(c => ({
      service_id: c.service_id,
      addon_id: c.addon_id,
      name: c.name,
      unit_price: c.unit_price,
      quantity: c.quantity,
      is_optional: c.is_optional,
    }))
    loadCatalog()
  }
})

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

function addComponent() {
  if (!catalogPick.value) return
  const picked = catalogOptions.value.find(c => c.key === catalogPick.value)
  if (!picked) return

  components.value.push({
    service_id: picked.type === 'service' ? picked.id : null,
    addon_id: picked.type === 'addon' ? picked.id : null,
    name: picked.name,
    unit_price: picked.price,
    quantity: 1,
    is_optional: false,
  })
  catalogPick.value = null
}

function removeComponent(index) {
  components.value.splice(index, 1)
}

function lineTotal(component) {
  return (Number(component.unit_price) || 0) * (Number(component.quantity) || 0)
}

const componentTotal = computed(() => components.value
  .filter(c => !c.is_optional)
  .reduce((sum, c) => sum + lineTotal(c), 0))

function finalPrice(values) {
  if (values.override_price !== null && values.override_price !== '' && values.override_price !== undefined) {
    return Number(values.override_price)
  }

  const discountValue = Number(values.discount_value) || 0
  const discount = values.discount_type === 'percent'
    ? componentTotal.value * (discountValue / 100)
    : (values.discount_type === 'fixed' ? discountValue : 0)

  return Math.max(0, componentTotal.value - discount)
}

async function onSubmit(values) {
  errorMessage.value = ''

  if (!components.value.length) {
    errorMessage.value = t('packages.errors.needsOneComponent')
    return
  }

  loading.value = true
  try {
    const payload = {
      ...values,
      components: components.value.map(c => ({
        service_id: c.service_id,
        addon_id: c.addon_id,
        quantity: c.quantity,
        is_optional: c.is_optional,
      })),
    }

    if (isEdit.value) {
      await updatePackageApi(props.pkg.id, payload)
      appStore.notify(t('packages.messages.updatedSuccess'))
    } else {
      await createPackageApi(payload)
      appStore.notify(t('packages.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'packages.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="760" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="packageSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-switch
              :model-value="values.is_active"
              :label="t('services.activeSwitchLabel')"
              color="primary"
              hide-details
              @update:model-value="setFieldValue('is_active', $event)"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.description" :label="t('fields.description')" rows="2" :error-messages="errors.description" @update:model-value="setFieldValue('description', $event)" />
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <div class="text-subtitle-2 mb-2">{{ t('packages.components') }}</div>

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
          <v-btn icon="mdi-plus" variant="tonal" @click="addComponent" />
        </div>

        <v-table density="compact" class="mb-4">
          <thead>
            <tr>
              <th>{{ t('fields.name') }}</th>
              <th style="width: 100px">{{ t('fields.unitPrice') }}</th>
              <th style="width: 90px">{{ t('fields.quantity') }}</th>
              <th style="width: 100px">{{ t('fields.total') }}</th>
              <th style="width: 110px">{{ t('packages.optional') }}</th>
              <th style="width: 40px" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(component, index) in components" :key="index">
              <td>{{ component.name }}</td>
              <td>${{ component.unit_price }}</td>
              <td>
                <v-text-field v-model.number="component.quantity" type="number" min="1" density="compact" hide-details />
              </td>
              <td>${{ lineTotal(component).toFixed(2) }}</td>
              <td>
                <v-checkbox v-model="component.is_optional" density="compact" hide-details />
              </td>
              <td>
                <v-btn icon="mdi-close" size="small" variant="text" @click="removeComponent(index)" />
              </td>
            </tr>
            <tr v-if="!components.length">
              <td colspan="6" class="text-center text-medium-emphasis py-4">{{ t('packages.noComponentsYet') }}</td>
            </tr>
          </tbody>
        </v-table>

        <v-row>
          <v-col cols="12" sm="4">
            <v-select
              :model-value="values.discount_type"
              :label="t('packages.discountType')"
              :items="DISCOUNT_TYPES"
              @update:model-value="setFieldValue('discount_type', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              :model-value="values.discount_value"
              :label="t('packages.discountValue')"
              type="number"
              step="0.01"
              :disabled="!values.discount_type"
              :error-messages="errors.discount_value"
              @update:model-value="setFieldValue('discount_value', $event)"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field :model-value="values.override_price" :label="t('packages.overridePrice')" type="number" step="0.01" prefix="$" :error-messages="errors.override_price" @update:model-value="setFieldValue('override_price', $event)" />
          </v-col>
        </v-row>

        <div class="d-flex justify-end mb-4">
          <div style="min-width: 220px">
            <div class="text-body-2 d-flex justify-space-between">
              <span>{{ t('packages.componentTotal') }}</span><span>${{ componentTotal.toFixed(2) }}</span>
            </div>
            <div class="text-h6 d-flex justify-space-between">
              <span>{{ t('packages.finalPrice') }}</span><span>${{ finalPrice(values).toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
