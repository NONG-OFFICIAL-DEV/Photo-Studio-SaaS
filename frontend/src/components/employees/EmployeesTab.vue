<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmployeeProfileDialog from '@/components/employees/EmployeeProfileDialog.vue'
import { getUsersApi } from '@/apis/user.api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const users = ref([])
const loading = ref(false)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('employees.payType'), key: 'pay_type' },
  { title: t('employees.basePay'), key: 'base_pay' },
  { title: t('employees.commissionRate'), key: 'commission_rate' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function load() {
  loading.value = true
  try {
    const { data } = await getUsersApi()
    users.value = data.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

const profileDialog = ref(false)
const editingEmployee = ref(null)

function openEdit(user) {
  editingEmployee.value = user
  profileDialog.value = true
}

const canUpdate = computed(() => auth.hasPermission('users.update'))
</script>

<template>
  <div>
    <v-card variant="flat" border rounded="lg" class="pa-4">
      <v-data-table :headers="headers" :items="users" :loading="loading" item-value="id">
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

    <EmployeeProfileDialog v-model="profileDialog" :employee="editingEmployee" @saved="load" />
  </div>
</template>
