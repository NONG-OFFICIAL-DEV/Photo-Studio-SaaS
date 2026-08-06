<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import ServiceFormDialog from '@/components/services/ServiceFormDialog.vue'
import ServiceCategoryManagerDialog from '@/components/services/ServiceCategoryManagerDialog.vue'
import ServiceAddOnManagerDialog from '@/components/services/ServiceAddOnManagerDialog.vue'
import { getServicesApi, deleteServiceApi } from '@/apis/service.api'
import { useServiceCategoriesStore } from '@/stores/serviceCategories'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { formatCurrency } from '@/utils/currencyFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const categoriesStore = useServiceCategoriesStore()
categoriesStore.fetch()

const tableRef = ref(null)

const PRICING_UNIT_SUFFIX = computed(() => ({
  fixed: '',
  per_hour: t('services.pricingUnitSuffix.perHour'),
  per_person: t('services.pricingUnitSuffix.perPerson'),
  per_photo: t('services.pricingUnitSuffix.perPhoto'),
}))

const statusFilterItems = computed(() => [
  { title: t('fields.active'), value: '1' },
  { title: t('fields.inactive'), value: '0' },
])

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('fields.category'), key: 'category' },
  { title: t('fields.price'), key: 'price' },
  { title: t('fields.duration'), key: 'duration' },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ category_id: null, is_active: null })

async function fetchServices(params) {
  const { data } = await getServicesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingService = ref(null)
const categoryManagerDialog = ref(false)
const addOnManagerDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingService.value = null
  formDialog.value = true
}

function openEdit(service) {
  editingService.value = service
  formDialog.value = true
}

function askDelete(service) {
  deleteTarget.value = service
  confirmDelete.value = true
}

async function confirmDeleteService() {
  await deleteServiceApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('services.messages.serviceDeleted'))
  tableRef.value?.refresh()
}

function formatPrice(service) {
  const amount = formatCurrency(service.price)
  return service.pricing_unit === 'fixed' ? amount : `${amount} ${PRICING_UNIT_SUFFIX.value[service.pricing_unit]}`
}

const canCreate = computed(() => auth.hasPermission('services.create'))
const canUpdate = computed(() => auth.hasPermission('services.update'))
const canDelete = computed(() => auth.hasPermission('services.delete'))
</script>

<template>
  <div>
    <AppToolbar :title="t('services.title')" :subtitle="t('services.subtitle')">
      <template #actions>
        <v-btn v-if="canUpdate" variant="outlined" prepend-icon="mdi-tag-multiple-outline" @click="categoryManagerDialog = true">
          {{ t('services.categories') }}
        </v-btn>
        <v-btn v-if="canUpdate" variant="outlined" prepend-icon="mdi-plus-box-multiple-outline" @click="addOnManagerDialog = true">
          {{ t('services.addOns') }}
        </v-btn>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('services.newService') }}</v-btn>
      </template>
    </AppToolbar>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.category_id"
          :label="t('fields.category')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="categoriesStore.categories"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.is_active"
          :label="t('fields.status')"
          clearable
          density="compact"
          :items="statusFilterItems"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchServices"
        :filters="filters"
        item-label="services"
      >
        <template #[`item.category`]="{ item }">
          {{ item.category?.name || '—' }}
        </template>

        <template #[`item.price`]="{ item }">
          {{ formatPrice(item) }}
        </template>

        <template #[`item.duration`]="{ item }">
          {{ item.duration_minutes ? `${item.duration_minutes} min` : '—' }}
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip :color="item.is_active ? 'success' : 'default'" size="small" variant="tonal">
            {{ item.is_active ? t('fields.active') : t('fields.inactive') }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="openEdit(item)" />
          <v-btn v-if="canDelete" icon="mdi-delete-outline" size="small" variant="text" color="error" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <ServiceFormDialog v-model="formDialog" :service="editingService" @saved="tableRef?.refresh()" />

    <ServiceCategoryManagerDialog v-model="categoryManagerDialog" @update:model-value="tableRef?.refresh()" />

    <ServiceAddOnManagerDialog v-model="addOnManagerDialog" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('services.deleteServiceTitle')"
      :message="t('services.deleteServiceMessage', { name: deleteTarget?.name })"
      @confirm="confirmDeleteService"
    />
  </div>
</template>
