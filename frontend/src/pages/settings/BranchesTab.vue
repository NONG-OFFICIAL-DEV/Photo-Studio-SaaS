<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PlanLimitAlert from '@/components/common/PlanLimitAlert.vue'
import AppStatusChip from '@/components/common/AppStatusChip.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import BranchFormDialog from '@/components/settings/BranchFormDialog.vue'
import { deleteBranchApi } from '@/apis/branch.api'
import { getPlanLimitsApi } from '@/apis/plan-limit.api'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import { useBranchStore } from '@/stores/branches'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const auth = useAuthStore()
const appStore = useAppStore()
const branchStore = useBranchStore()

const branches = computed(() => branchStore.branches)
const loading = ref(false)
const limits = ref({ max_branches: null, branches_count: 0 })

const headers = computed(() => [
  { title: t('fields.name'), key: 'name' },
  { title: t('branches.fields.address'), key: 'address' },
  { title: t('branches.fields.phone'), key: 'phone' },
  { title: t('branches.fields.status'), key: 'is_active' },
  { title: t('common.actions'), key: 'actions', sortable: false, align: 'end' },
])

async function load() {
  loading.value = true
  try {
    const [, { data: limitsData }] = await Promise.all([
      branchStore.fetch(true),
      getPlanLimitsApi(),
    ])
    limits.value = limitsData.data
  } finally {
    loading.value = false
  }
}

onMounted(load)

const formDialog = ref(false)
const editingBranch = ref(null)
const confirmDelete = ref(false)
const branchToDelete = ref(null)
const deleting = ref(false)

function openCreate() {
  editingBranch.value = null
  formDialog.value = true
}

function openEdit(branch) {
  editingBranch.value = branch
  formDialog.value = true
}

function askDelete(branch) {
  branchToDelete.value = branch
  confirmDelete.value = true
}

async function confirmDeleteBranch() {
  deleting.value = true
  try {
    await deleteBranchApi(branchToDelete.value.id)
    appStore.notify(t('branches.messages.deletedSuccess'))
    confirmDelete.value = false
    await load()
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'branches.messages.deleteError'), 'error')
  } finally {
    deleting.value = false
  }
}

const canCreate = computed(() => auth.hasPermission('branches.create'))
const canUpdate = computed(() => auth.hasPermission('branches.update'))
const canDelete = computed(() => auth.hasPermission('branches.delete'))
const atBranchLimit = computed(() => limits.value.max_branches !== null && limits.value.branches_count >= limits.value.max_branches)
</script>

<template>
  <div>
    <PlanLimitAlert :current="limits.branches_count" :limit="limits.max_branches" :resource="t('branches.title').toLowerCase()" />

    <div class="d-flex justify-end mb-4">
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" :disabled="atBranchLimit" @click="openCreate">
        {{ t('branches.newBranch') }}
      </v-btn>
    </div>

    <v-card variant="flat" border rounded="lg" class="pa-4">
      <v-data-table :headers="headers" :items="branches" :loading="loading" item-value="id">
        <template #[`item.address`]="{ item }">
          {{ item.address ?? '—' }}
        </template>

        <template #[`item.phone`]="{ item }">
          {{ item.phone ?? '—' }}
        </template>

        <template #[`item.is_active`]="{ item }">
          <AppStatusChip :status="item.is_active ? 'active' : 'inactive'" size="small" />
        </template>

        <template #[`item.actions`]="{ item }">
          <v-btn v-if="canUpdate" icon="mdi-pencil-outline" size="small" variant="text" color="primary" @click="openEdit(item)" />
          <v-btn v-if="canDelete" icon="mdi-delete-outline" size="small" variant="text" color="error" @click="askDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <BranchFormDialog v-model="formDialog" :branch="editingBranch" @saved="load" />
    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('branches.deleteTitle')"
      :message="t('branches.deleteMessage', { name: branchToDelete?.name })"
      :loading="deleting"
      @confirm="confirmDeleteBranch"
    />
  </div>
</template>
