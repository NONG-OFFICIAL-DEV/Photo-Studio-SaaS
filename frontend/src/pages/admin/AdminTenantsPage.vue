<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import AdminSubscriptionDialog from '@/components/admin/AdminSubscriptionDialog.vue'
import AdminTenantRolePermissionDialog from '@/components/admin/AdminTenantRolePermissionDialog.vue'
import AdminTenantDeleteDialog from '@/components/admin/AdminTenantDeleteDialog.vue'
import {
  getAdminTenantsApi,
  suspendAdminTenantApi,
  activateAdminTenantApi,
} from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const appStore = useAppStore()
const tableRef = ref(null)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('fields.email'), key: 'email' },
  { title: t('admin.tenants.plan'), key: 'plan', sortable: false },
  { title: t('admin.tenants.subscriptionStatus'), key: 'subscription', sortable: false },
  { title: t('admin.tenants.users'), key: 'users_count' },
  { title: t('fields.status'), key: 'is_active', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

const filters = ref({ status: null })

async function fetchTenants(params) {
  const { data } = await getAdminTenantsApi(params)
  return { items: data.data, total: data.meta.total }
}

const confirmDialog = ref(false)
const confirmTarget = ref(null)
const confirmAction = ref(null)
const actionLoading = ref(false)

const subscriptionDialog = ref(false)
const subscriptionTarget = ref(null)

function openSubscriptionDialog(tenant) {
  subscriptionTarget.value = tenant
  subscriptionDialog.value = true
}

const permissionsDialog = ref(false)
const permissionsTarget = ref(null)

function openPermissionsDialog(tenant) {
  permissionsTarget.value = tenant
  permissionsDialog.value = true
}

const deleteDialog = ref(false)
const deleteTarget = ref(null)

function openDeleteDialog(tenant) {
  deleteTarget.value = tenant
  deleteDialog.value = true
}

function askSuspend(tenant) {
  confirmTarget.value = tenant
  confirmAction.value = 'suspend'
  confirmDialog.value = true
}

function askActivate(tenant) {
  confirmTarget.value = tenant
  confirmAction.value = 'activate'
  confirmDialog.value = true
}

const confirmTitle = computed(() =>
  confirmAction.value === 'suspend' ? t('admin.tenants.confirmSuspendTitle') : t('admin.tenants.confirmActivateTitle'))
const confirmMessage = computed(() =>
  t('admin.tenants.confirmMessage', { name: confirmTarget.value?.name }))

async function confirmToggle() {
  actionLoading.value = true
  try {
    if (confirmAction.value === 'suspend') {
      await suspendAdminTenantApi(confirmTarget.value.id)
      appStore.notify(t('admin.tenants.messages.suspended'))
    } else {
      await activateAdminTenantApi(confirmTarget.value.id)
      appStore.notify(t('admin.tenants.messages.activated'))
    }
    confirmDialog.value = false
    tableRef.value?.refresh()
  } finally {
    actionLoading.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.tenants.title')" :subtitle="t('admin.tenants.subtitle')" />

    <v-row class="mb-2">
      <v-col cols="12" sm="4" md="3">
        <v-select
          v-model="filters.status"
          :label="t('fields.status')"
          clearable
          :items="[
            { title: t('common.status.active'), value: 'active' },
            { title: t('common.status.suspended'), value: 'suspended' },
          ]"
        />
      </v-col>
    </v-row>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchTenants" :filters="filters" item-label="tenants">
        <template #[`item.plan`]="{ item }">
          {{ item.subscription?.plan?.name ?? '—' }}
        </template>

        <template #[`item.subscription`]="{ item }">
          <AppStatusChip v-if="item.subscription" :status="item.subscription.status" />
          <span v-else>—</span>
        </template>

        <template #[`item.is_active`]="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'suspended'" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn
            size="small"
            variant="text"
            prepend-icon="mdi-credit-card-outline"
            @click="openSubscriptionDialog(item)"
          >
            {{ t('admin.tenants.actions.manageSubscription') }}
          </v-btn>
          <v-btn
            size="small"
            variant="text"
            prepend-icon="mdi-shield-account-outline"
            @click="openPermissionsDialog(item)"
          >
            {{ t('admin.tenants.actions.managePermissions') }}
          </v-btn>
          <v-btn
            v-if="item.is_active"
            size="small"
            variant="text"
            color="error"
            prepend-icon="mdi-account-cancel-outline"
            @click="askSuspend(item)"
          >
            {{ t('admin.tenants.actions.suspend') }}
          </v-btn>
          <v-btn
            v-else
            size="small"
            variant="text"
            color="success"
            prepend-icon="mdi-account-check-outline"
            @click="askActivate(item)"
          >
            {{ t('admin.tenants.actions.activate') }}
          </v-btn>
          <v-btn
            size="small"
            variant="text"
            color="error"
            prepend-icon="mdi-delete-alert-outline"
            @click="openDeleteDialog(item)"
          >
            {{ t('admin.tenants.actions.delete') }}
          </v-btn>
        </template>
      </AppTable>
    </v-card>

    <AppConfirmDialog
      v-model="confirmDialog"
      :title="confirmTitle"
      :message="confirmMessage"
      :color="confirmAction === 'suspend' ? 'error' : 'success'"
      :loading="actionLoading"
      @confirm="confirmToggle"
    />

    <AdminSubscriptionDialog
      v-model="subscriptionDialog"
      :tenant="subscriptionTarget"
      @changed="tableRef?.refresh()"
    />

    <AdminTenantRolePermissionDialog v-model="permissionsDialog" :tenant="permissionsTarget" />

    <AdminTenantDeleteDialog
      v-model="deleteDialog"
      :tenant="deleteTarget"
      @deleted="tableRef?.refresh()"
    />
  </div>
</template>
