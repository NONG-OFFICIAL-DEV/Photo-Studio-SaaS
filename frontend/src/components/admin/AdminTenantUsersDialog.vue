<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import {
  getAdminTenantUsersApi,
  deactivateAdminTenantUserApi,
  reactivateAdminTenantUserApi,
  sendAdminUserPasswordResetApi,
} from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { formatDate } from '@/utils/dateFormat'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tenant: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const users = ref([])
const rowLoading = ref(null)

async function fetchUsers() {
  if (!props.tenant) return
  loading.value = true
  try {
    const { data } = await getAdminTenantUsersApi(props.tenant.id)
    users.value = data.data
  } finally {
    loading.value = false
  }
}

watch(() => props.modelValue, (open) => {
  if (open) fetchUsers()
})

const confirmDialog = ref(false)
const confirmTarget = ref(null)
const confirmAction = ref(null)
const confirmLoading = ref(false)

function askDeactivate(user) {
  confirmTarget.value = user
  confirmAction.value = 'deactivate'
  confirmDialog.value = true
}

function askReactivate(user) {
  confirmTarget.value = user
  confirmAction.value = 'reactivate'
  confirmDialog.value = true
}

async function confirmToggle() {
  confirmLoading.value = true
  try {
    if (confirmAction.value === 'deactivate') {
      await deactivateAdminTenantUserApi(props.tenant.id, confirmTarget.value.id)
      appStore.notify(t('admin.tenantUsers.messages.deactivatedSuccess'))
    } else {
      await reactivateAdminTenantUserApi(props.tenant.id, confirmTarget.value.id)
      appStore.notify(t('admin.tenantUsers.messages.reactivatedSuccess'))
    }
    confirmDialog.value = false
    await fetchUsers()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.tenantUsers.messages.actionError'), 'error')
  } finally {
    confirmLoading.value = false
  }
}

async function sendPasswordReset(user) {
  rowLoading.value = user.id
  try {
    await sendAdminUserPasswordResetApi(props.tenant.id, user.id)
    appStore.notify(t('admin.tenantUsers.messages.resetLinkSent'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.tenantUsers.messages.actionError'), 'error')
  } finally {
    rowLoading.value = null
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('admin.tenantUsers.dialogTitle', { name: tenant?.name })"
    max-width="800"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>{{ t('fields.name') }}</th>
          <th>{{ t('fields.email') }}</th>
          <th>{{ t('employees.role') }}</th>
          <th>{{ t('fields.status') }}</th>
          <th>{{ t('admin.tenantUsers.lastLogin') }}</th>
          <th class="text-end">{{ t('common.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td>{{ user.name }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.roles?.join(', ') || '—' }}</td>
          <td><AppStatusChip :status="user.status" size="small" /></td>
          <td>{{ user.last_login_at ? formatDate(user.last_login_at) : '—' }}</td>
          <td class="text-end">
            <v-menu>
              <template #activator="{ props: menuProps }">
                <v-btn icon="mdi-dots-vertical" size="small" variant="text" :loading="rowLoading === user.id" v-bind="menuProps" />
              </template>
              <v-list density="compact" min-width="200">
                <v-list-item
                  :title="t('admin.tenantUsers.actions.sendPasswordReset')"
                  prepend-icon="mdi-email-lock-outline"
                  @click="sendPasswordReset(user)"
                />
                <v-list-item
                  v-if="user.status === 'active'"
                  class="text-warning"
                  :title="t('admin.tenantUsers.actions.deactivate')"
                  prepend-icon="mdi-account-cancel-outline"
                  @click="askDeactivate(user)"
                />
                <v-list-item
                  v-else
                  class="text-success"
                  :title="t('admin.tenantUsers.actions.reactivate')"
                  prepend-icon="mdi-account-check-outline"
                  @click="askReactivate(user)"
                />
              </v-list>
            </v-menu>
          </td>
        </tr>
      </tbody>
    </v-table>

    <template #actions>
      <v-btn variant="text" @click="emit('update:modelValue', false)">{{ t('common.close') }}</v-btn>
    </template>
  </AppDialog>

  <AppConfirmDialog
    v-model="confirmDialog"
    :title="confirmAction === 'deactivate' ? t('admin.tenantUsers.confirmDeactivateTitle') : t('admin.tenantUsers.confirmReactivateTitle')"
    :message="t('admin.tenantUsers.confirmMessage', { name: confirmTarget?.name })"
    :color="confirmAction === 'deactivate' ? 'warning' : 'success'"
    :loading="confirmLoading"
    @confirm="confirmToggle"
  />
</template>
