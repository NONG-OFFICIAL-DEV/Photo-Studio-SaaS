<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import PackageFormDialog from '@/components/packages/PackageFormDialog.vue'
import { getPackagesApi, deletePackageApi } from '@/apis/package.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tableRef = ref(null)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('packages.components'), key: 'components', sortable: false },
  { title: t('packages.componentTotal'), key: 'component_total', sortable: false },
  { title: t('packages.finalPrice'), key: 'final_price', sortable: false },
  { title: t('fields.active'), key: 'is_active', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ is_active: null })

async function fetchPackages(params) {
  const { data } = await getPackagesApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingPackage = ref(null)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingPackage.value = null
  formDialog.value = true
}

function openEdit(pkg) {
  editingPackage.value = pkg
  formDialog.value = true
}

function askDelete(pkg) {
  deleteTarget.value = pkg
  confirmDelete.value = true
}

async function confirmDeletePackage() {
  await deletePackageApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('packages.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

const canCreate = computed(() => auth.hasPermission('packages.create'))
const canUpdate = computed(() => auth.hasPermission('packages.update'))
const canDelete = computed(() => auth.hasPermission('packages.delete'))
</script>

<template>
  <div>
    <AppToolbar :title="t('packages.title')" :subtitle="t('packages.subtitle')">
      <template #actions>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('packages.newPackage') }}</v-btn>
      </template>
    </AppToolbar>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchPackages"
        :filters="filters"
        item-label="packages"
      >
        <template #[`item.name`]="{ item }">
          <span class="cursor-pointer" @click="canUpdate && openEdit(item)">{{ item.name }}</span>
        </template>

        <template #[`item.components`]="{ item }">
          {{ t('orders.itemsCount', { count: item.components?.length ?? 0 }) }}
        </template>

        <template #[`item.component_total`]="{ item }">
          ${{ item.component_total }}
        </template>

        <template #[`item.final_price`]="{ item }">
          ${{ item.final_price }}
        </template>

        <template #[`item.is_active`]="{ item }">
          <v-chip :color="item.is_active ? 'success' : 'default'" size="small" variant="tonal">
            {{ item.is_active ? t('fields.active') : t('fields.inactive') }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
          <v-btn v-if="canDelete" icon="mdi-trash-can-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <PackageFormDialog v-model="formDialog" :pkg="editingPackage" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('packages.dialogs.deleteConfirmTitle')"
      :message="t('packages.dialogs.deleteConfirmMessage', { name: deleteTarget?.name })"
      @confirm="confirmDeletePackage"
    />
  </div>
</template>
