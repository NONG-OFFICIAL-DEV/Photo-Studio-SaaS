<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import InventoryItemFormDialog from '@/components/inventory/InventoryItemFormDialog.vue'
import InventoryItemDetailDialog from '@/components/inventory/InventoryItemDetailDialog.vue'
import { getInventoryItemsApi, deleteInventoryItemApi } from '@/apis/inventory.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { useBranchStore } from '@/stores/branches'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const branchStore = useBranchStore()
branchStore.fetch()
const tableRef = ref(null)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('inventory.sku'), key: 'sku' },
  { title: t('fields.category'), key: 'category' },
  { title: t('inventory.quantityOnHand'), key: 'quantity_on_hand', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ low_stock: null, is_active: null, branch_id: null })

async function fetchItems(params) {
  const { data } = await getInventoryItemsApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingItem = ref(null)
const detailDialog = ref(false)
const selectedItemId = ref(null)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingItem.value = null
  formDialog.value = true
}

function openEdit(item) {
  editingItem.value = item
  formDialog.value = true
}

function openDetail(item) {
  selectedItemId.value = item.id
  detailDialog.value = true
}

function askDelete(item) {
  deleteTarget.value = item
  confirmDelete.value = true
}

async function confirmDeleteItem() {
  await deleteInventoryItemApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('inventory.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

const canCreate = computed(() => auth.hasPermission('inventory.create'))
const canUpdate = computed(() => auth.hasPermission('inventory.update'))
const canDelete = computed(() => auth.hasPermission('inventory.delete'))
</script>

<template>
  <div>
    <AppToolbar :title="t('inventory.title')" :subtitle="t('inventory.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('inventory.newItem') }}</v-btn>
      </template>
    </AppToolbar>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-switch v-model="filters.low_stock" :label="t('inventory.lowStockOnly')" color="warning" hide-details density="compact" />
      </v-col>
      <v-col v-if="branchStore.branches.length > 1" cols="6" sm="3">
        <v-select
          v-model="filters.branch_id"
          :label="t('fields.branch')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="branchStore.branches"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchItems"
        :filters="filters"
        item-label="items"
      >
        <template #[`item.name`]="{ item }">
          <span class="cursor-pointer" @click="openDetail(item)">{{ item.name }}</span>
        </template>

        <template #[`item.sku`]="{ item }">
          {{ item.sku || '—' }}
        </template>

        <template #[`item.category`]="{ item }">
          {{ item.category || '—' }}
        </template>

        <template #[`item.quantity_on_hand`]="{ item }">
          {{ item.quantity_on_hand }} {{ item.unit }}
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip :color="item.is_low_stock ? 'error' : 'success'" size="small" variant="tonal">
            {{ item.is_low_stock ? t('inventory.lowStock') : t('inventory.inStock') }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" variant="text" color="info" @click="openDetail(item)" />
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="openEdit(item)" />
          <v-btn v-if="canDelete" icon="mdi-trash-can-outline" size="small" variant="text" color="error" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <InventoryItemFormDialog v-model="formDialog" :item="editingItem" @saved="tableRef?.refresh()" />

    <InventoryItemDetailDialog v-model="detailDialog" :item-id="selectedItemId" @changed="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('inventory.deleteConfirmTitle')"
      :message="t('inventory.deleteConfirmMessage', { name: deleteTarget?.name })"
      @confirm="confirmDeleteItem"
    />
  </div>
</template>
