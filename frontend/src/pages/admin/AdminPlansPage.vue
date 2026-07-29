<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import AppTable from '@/components/common/AppTable.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import PlanFormDialog from '@/components/admin/PlanFormDialog.vue'
import { getAdminPlansApi, deleteAdminPlanApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const appStore = useAppStore()
const tableRef = ref(null)

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('admin.plans.fields.code'), key: 'code' },
  { title: t('admin.plans.fields.priceMonthly'), key: 'price_monthly' },
  { title: t('admin.plans.fields.maxUsers'), key: 'max_users' },
  { title: t('admin.plans.subscriptionsCount'), key: 'subscriptions_count', sortable: false },
  { title: t('fields.status'), key: 'is_active', sortable: false },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function fetchPlans(params) {
  const { data } = await getAdminPlansApi(params)
  return { items: data.data, total: data.meta.total }
}

const formDialog = ref(false)
const editingPlan = ref(null)
const confirmDelete = ref(false)
const deleteTarget = ref(null)
const deleteLoading = ref(false)

function openCreate() {
  editingPlan.value = null
  formDialog.value = true
}

function openEdit(plan) {
  editingPlan.value = plan
  formDialog.value = true
}

function askDelete(plan) {
  deleteTarget.value = plan
  confirmDelete.value = true
}

async function confirmDeletePlan() {
  deleteLoading.value = true
  try {
    await deleteAdminPlanApi(deleteTarget.value.id)
    appStore.notify(t('admin.plans.messages.deletedSuccess'))
    confirmDelete.value = false
    tableRef.value?.refresh()
  } catch (error) {
    appStore.notify(error.response?.data?.message || t('admin.plans.messages.deleteError'), 'error')
    confirmDelete.value = false
  } finally {
    deleteLoading.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.plans.title')" :subtitle="t('admin.plans.subtitle')">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">{{ t('admin.plans.newPlan') }}</v-btn>
      </template>
    </AppToolbar>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <AppTable ref="tableRef" :headers="headers" :fetch-fn="fetchPlans" :show-search="true" item-label="plans">
        <template #[`item.price_monthly`]="{ item }">
          ${{ Number(item.price_monthly).toFixed(2) }}
        </template>

        <template #[`item.is_active`]="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn icon="mdi-pencil-outline" variant="text" size="small" @click="openEdit(item)" />
          <v-btn icon="mdi-delete-outline" variant="text" size="small" color="error" @click="askDelete(item)" />
        </template>
      </AppTable>
    </v-card>

    <PlanFormDialog v-model="formDialog" :plan="editingPlan" @saved="tableRef?.refresh()" />

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('admin.plans.confirmDeleteTitle')"
      :message="t('admin.plans.confirmDeleteMessage', { name: deleteTarget?.name })"
      :loading="deleteLoading"
      @confirm="confirmDeletePlan"
    />
  </div>
</template>
