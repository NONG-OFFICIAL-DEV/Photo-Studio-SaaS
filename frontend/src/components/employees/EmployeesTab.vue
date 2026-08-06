<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PlanLimitAlert from '@/components/common/PlanLimitAlert.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import EmployeeFormDialog from '@/components/employees/EmployeeFormDialog.vue'
import EmployeeProfileDialog from '@/components/employees/EmployeeProfileDialog.vue'
import { getUsersApi, deactivateUserApi, reactivateUserApi } from '@/apis/user.api'
import { getPlanLimitsApi } from '@/apis/plan-limit.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'
import { formatCurrency } from '@/utils/currencyFormat'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()

const users = ref([])
const loading = ref(false)
const limits = ref({ max_users: null, users_count: 0 })

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('employees.role'), key: 'role' },
  { title: t('employees.payType'), key: 'pay_type' },
  { title: t('employees.basePay'), key: 'base_pay' },
  { title: t('employees.commissionRate'), key: 'commission_rate' },
  { title: t('fields.status'), key: 'status' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function load() {
  loading.value = true
  try {
    const [{ data: usersData }, { data: limitsData }] = await Promise.all([
      getUsersApi({ include_inactive: 1 }),
      getPlanLimitsApi(),
    ])
    users.value = usersData.data
    limits.value = limitsData.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

const formDialog = ref(false)
const profileDialog = ref(false)
const editingEmployee = ref(null)
const confirmDeactivate = ref(false)
const deactivateTarget = ref(null)
const statusActionLoading = ref(false)

function openEdit(user) {
  editingEmployee.value = user
  profileDialog.value = true
}

function askDeactivate(user) {
  deactivateTarget.value = user
  confirmDeactivate.value = true
}

async function confirmDeactivateEmployee() {
  statusActionLoading.value = true
  try {
    await deactivateUserApi(deactivateTarget.value.id)
    appStore.notify(t('employees.messages.deactivatedSuccess'))
    confirmDeactivate.value = false
    await load()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'employees.messages.deactivateError'), 'error')
  } finally {
    statusActionLoading.value = false
  }
}

async function reactivateEmployee(user) {
  statusActionLoading.value = true
  try {
    await reactivateUserApi(user.id)
    appStore.notify(t('employees.messages.reactivatedSuccess'))
    await load()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'employees.messages.reactivateError'), 'error')
  } finally {
    statusActionLoading.value = false
  }
}

const canCreate = computed(() => auth.hasPermission('users.create'))
const canUpdate = computed(() => auth.hasPermission('users.update'))
const canDeactivate = computed(() => auth.hasPermission('users.delete'))
const atUserLimit = computed(() => limits.value.max_users !== null && limits.value.users_count >= limits.value.max_users)
</script>

<template>
  <div>
    <PlanLimitAlert :current="limits.users_count" :limit="limits.max_users" :resource="t('employees.title').toLowerCase()" />

    <div class="d-flex justify-end mb-4">
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" :disabled="atUserLimit" @click="formDialog = true">
        {{ t('employees.newEmployee') }}
      </v-btn>
    </div>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <v-data-table :headers="headers" :items="users" :loading="loading" item-value="id">
        <template #[`item.role`]="{ item }">
          {{ item.roles?.[0] ? t(`employees.roles.${item.roles[0]}`) : '—' }}
        </template>

        <template #[`item.pay_type`]="{ item }">
          {{ item.pay_type ? t(`employees.payTypes.${item.pay_type}`) : '—' }}
        </template>

        <template #[`item.base_pay`]="{ item }">
          {{ item.base_pay !== null ? formatCurrency(item.base_pay) : '—' }}
        </template>

        <template #[`item.commission_rate`]="{ item }">
          {{ item.commission_rate !== null ? `${item.commission_rate}%` : '—' }}
        </template>

        <template #[`item.status`]="{ item }">
          <AppStatusChip :status="item.status" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="openEdit(item)" />
          <v-btn
            v-if="canDeactivate && item.status === 'active' && item.id !== auth.user?.id"
            icon="mdi-account-off-outline"
            size="small"
            variant="text"
            color="error"
            :title="t('employees.deactivate')"
            @click="askDeactivate(item)"
          />
          <v-btn
            v-if="canDeactivate && item.status === 'inactive'"
            icon="mdi-account-check-outline"
            size="small"
            variant="text"
            color="success"
            :title="t('employees.reactivate')"
            :loading="statusActionLoading"
            @click="reactivateEmployee(item)"
          />
        </template>
      </v-data-table>
    </v-card>

    <EmployeeFormDialog v-model="formDialog" @saved="load" />
    <EmployeeProfileDialog v-model="profileDialog" :employee="editingEmployee" @saved="load" />
    <AppConfirmDialog
      v-model="confirmDeactivate"
      :title="t('employees.confirmDeactivateTitle')"
      :message="t('employees.confirmDeactivateMessage', { name: deactivateTarget?.name })"
      :loading="statusActionLoading"
      @confirm="confirmDeactivateEmployee"
    />
  </div>
</template>
