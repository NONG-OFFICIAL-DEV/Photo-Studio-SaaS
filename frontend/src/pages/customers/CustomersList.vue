<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import CustomerFormDialog from '@/components/customers/CustomerFormDialog.vue'
import CustomerDetailDialog from '@/components/customers/CustomerDetailDialog.vue'
import CustomerBlacklistDialog from '@/components/customers/CustomerBlacklistDialog.vue'
import CustomerImportDialog from '@/components/customers/CustomerImportDialog.vue'
import CustomerTagManagerDialog from '@/components/customers/CustomerTagManagerDialog.vue'
import {
  getCustomersApi,
  deleteCustomerApi,
  toggleCustomerFavoriteApi,
  unblacklistCustomerApi,
  exportCustomersApi,
} from '@/apis/customer.api'
import { useCustomerTagsStore } from '@/stores/customerTags'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const tagsStore = useCustomerTagsStore()
tagsStore.fetch()

const tableRef = ref(null)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('fields.phone'), key: 'phone' },
  { title: t('fields.email'), key: 'email' },
  { title: t('fields.tags'), key: 'tags', sortable: false },
  { title: t('fields.status'), key: 'status', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ tag_id: null, is_favorite: null, is_blacklisted: null, gender: null })

async function fetchCustomers(params) {
  const { data } = await getCustomersApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingCustomer = ref(null)
const detailDialog = ref(false)
const selectedCustomerId = ref(null)
const blacklistDialog = ref(false)
const blacklistTargetId = ref(null)
const importDialog = ref(false)
const tagManagerDialog = ref(false)
const confirmDelete = ref(false)
const deleteTarget = ref(null)

function openCreate() {
  editingCustomer.value = null
  formDialog.value = true
}

function openEdit(customer) {
  editingCustomer.value = customer
  formDialog.value = true
}

function openDetail(customer) {
  selectedCustomerId.value = customer.id
  detailDialog.value = true
}

function openBlacklist(customer) {
  blacklistTargetId.value = customer.id
  blacklistDialog.value = true
}

async function unblacklist(customer) {
  await unblacklistCustomerApi(customer.id)
  appStore.notify(t('customers.messages.unblacklistedSuccess'))
  tableRef.value?.refresh()
}

async function toggleFavorite(customer) {
  await toggleCustomerFavoriteApi(customer.id)
  tableRef.value?.refresh()
}

function askDelete(customer) {
  deleteTarget.value = customer
  confirmDelete.value = true
}

async function confirmDeleteCustomer() {
  await deleteCustomerApi(deleteTarget.value.id)
  confirmDelete.value = false
  appStore.notify(t('customers.messages.deletedSuccess'))
  tableRef.value?.refresh()
}

async function exportCustomers(format) {
  const { data } = await exportCustomersApi(format, { search: filters.value.search })
  const url = window.URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href = url
  link.download = `customers.${format}`
  link.click()
  window.URL.revokeObjectURL(url)
}

const canCreate = computed(() => auth.hasPermission('customers.create'))
const canUpdate = computed(() => auth.hasPermission('customers.update'))
const canDelete = computed(() => auth.hasPermission('customers.delete'))
const canExport = computed(() => auth.hasPermission('customers.export'))
const canImport = computed(() => auth.hasPermission('customers.import'))
</script>

<template>
  <div>
    <AppToolbar :title="t('customers.title')" :subtitle="t('customers.subtitle')">
      <template #actions>
        <v-btn v-if="canImport" variant="outlined" prepend-icon="mdi-upload" @click="importDialog = true">{{ t('customers.actions.import') }}</v-btn>
        <v-menu v-if="canExport">
          <template #activator="{ props: menuProps }">
            <v-btn variant="outlined" prepend-icon="mdi-download" v-bind="menuProps">{{ t('customers.actions.export') }}</v-btn>
          </template>
          <v-list>
            <v-list-item :title="t('customers.actions.exportCsv')" @click="exportCustomers('csv')" />
            <v-list-item :title="t('customers.actions.exportExcel')" @click="exportCustomers('xlsx')" />
          </v-list>
        </v-menu>
        <v-btn variant="outlined" prepend-icon="mdi-tag-multiple-outline" @click="tagManagerDialog = true">{{ t('fields.tags') }}</v-btn>
        <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('customers.actions.addCustomer') }}</v-btn>
      </template>
    </AppToolbar>

    <v-row class="mb-2" dense>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.tag_id"
          :label="t('customers.filters.tag')"
          clearable
          density="compact"
          item-title="name"
          item-value="id"
          :items="tagsStore.tags"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-select
          v-model="filters.gender"
          :label="t('fields.gender')"
          clearable
          density="compact"
          :items="[
            { title: t('customers.genderOptions.male'), value: 'male' },
            { title: t('customers.genderOptions.female'), value: 'female' },
            { title: t('customers.genderOptions.other'), value: 'other' },
          ]"
        />
      </v-col>
      <v-col cols="6" sm="3">
        <v-checkbox v-model="filters.is_favorite" :label="t('customers.filters.favoritesOnly')" density="compact" hide-details true-value="1" :false-value="null" />
      </v-col>
      <v-col cols="6" sm="3">
        <v-checkbox v-model="filters.is_blacklisted" :label="t('customers.filters.blacklistedOnly')" density="compact" hide-details true-value="1" :false-value="null" />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable
        ref="tableRef"
        :headers="headers"
        :fetch-fn="fetchCustomers"
        :filters="filters"
        item-label="customers"
      >
        <template #[`item.name`]="{ item }">
          <div class="d-flex align-center ga-1">
            <v-icon v-if="item.is_favorite" icon="mdi-star" color="warning" size="16" />
            <span class="cursor-pointer" @click="openDetail(item)">{{ item.name }}</span>
          </div>
        </template>

        <template #[`item.tags`]="{ item }">
          <v-chip v-for="tag in item.tags" :key="tag.id" size="x-small" :color="tag.color" variant="tonal" label class="mr-1">
            {{ tag.name }}
          </v-chip>
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip
            v-if="item.is_blacklisted"
            status="blacklisted"
            :map="{ blacklisted: { color: 'error', label: t('customers.status.blacklisted') } }"
            size="small"
          />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-eye-outline" size="small" variant="text" @click="openDetail(item)" />
          <v-btn
            :icon="item.is_favorite ? 'mdi-star' : 'mdi-star-outline'"
            :color="item.is_favorite ? 'warning' : undefined"
            size="small"
            variant="text"
            @click="toggleFavorite(item)"
          />
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
          <v-btn
            v-if="canUpdate && !item.is_blacklisted"
            icon="mdi-account-cancel-outline"
            size="small"
            variant="text"
            @click="openBlacklist(item)"
          />
          <v-btn
            v-if="canUpdate && item.is_blacklisted"
            icon="mdi-account-check-outline"
            size="small"
            variant="text"
            @click="unblacklist(item)"
          />
          <v-btn v-if="canDelete" icon="mdi-delete-outline" size="small" variant="text" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <CustomerFormDialog
      v-model="formDialog"
      :customer="editingCustomer"
      @saved="tableRef?.refresh()"
    />

    <CustomerDetailDialog
      v-model="detailDialog"
      :customer-id="selectedCustomerId"
      @changed="tableRef?.refresh()"
    />

    <CustomerBlacklistDialog
      v-model="blacklistDialog"
      :customer-id="blacklistTargetId"
      @saved="tableRef?.refresh()"
    />

    <CustomerImportDialog v-model="importDialog" @imported="tableRef?.refresh()" />

    <CustomerTagManagerDialog v-model="tagManagerDialog" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('customers.dialogs.deleteCustomerConfirmTitle')"
      :message="t('customers.dialogs.deleteCustomerConfirmMessage', { name: deleteTarget?.name })"
      @confirm="confirmDeleteCustomer"
    />
  </div>
</template>
