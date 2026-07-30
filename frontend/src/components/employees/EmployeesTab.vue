<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PlanLimitAlert from '@/components/common/PlanLimitAlert.vue'
import EmployeeFormDialog from '@/components/employees/EmployeeFormDialog.vue'
import EmployeeProfileDialog from '@/components/employees/EmployeeProfileDialog.vue'
import { getUsersApi } from '@/apis/user.api'
import { getPlanLimitsApi } from '@/apis/plan-limit.api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const users = ref([])
const loading = ref(false)
const limits = ref({ max_users: null, users_count: 0 })

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('employees.role'), key: 'role' },
  { title: t('employees.payType'), key: 'pay_type' },
  { title: t('employees.basePay'), key: 'base_pay' },
  { title: t('employees.commissionRate'), key: 'commission_rate' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function load() {
  loading.value = true
  try {
    const [{ data: usersData }, { data: limitsData }] = await Promise.all([
      getUsersApi(),
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

function openEdit(user) {
  editingEmployee.value = user
  profileDialog.value = true
}

const canCreate = computed(() => auth.hasPermission('users.create'))
const canUpdate = computed(() => auth.hasPermission('users.update'))
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
          {{ item.base_pay !== null ? `$${item.base_pay}` : '—' }}
        </template>

        <template #[`item.commission_rate`]="{ item }">
          {{ item.commission_rate !== null ? `${item.commission_rate}%` : '—' }}
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" @click="openEdit(item)" />
        </template>
      </v-data-table>
    </v-card>

    <EmployeeFormDialog v-model="formDialog" @saved="load" />
    <EmployeeProfileDialog v-model="profileDialog" :employee="editingEmployee" @saved="load" />
  </div>
</template>
