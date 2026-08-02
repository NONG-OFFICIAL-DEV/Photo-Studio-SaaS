<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import RolePermissionMatrix from '@/components/common/RolePermissionMatrix.vue'
import { getAdminRolePermissionsApi, updateAdminRolePermissionsApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(true)
const saving = ref(false)
const catalog = ref({})
const roles = ref([])
const matrixRef = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await getAdminRolePermissionsApi()
    catalog.value = data.data.catalog
    roles.value = data.data.roles
  } finally {
    loading.value = false
  }
}

load()

const lockedLabel = computed(() => roles.value.find((r) => r.locked)?.label ?? 'Owner')

async function handleSave(role, permissions) {
  saving.value = true
  try {
    await updateAdminRolePermissionsApi(role, permissions)
    matrixRef.value?.markSaved(role)
    appStore.notify(t('admin.rolePermissions.messages.savedSuccess'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.rolePermissions.messages.saveError'), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.rolePermissions.title')" :subtitle="t('admin.rolePermissions.subtitle')" />

    <v-skeleton-loader v-if="loading" type="article" />

    <RolePermissionMatrix
      v-else
      ref="matrixRef"
      :catalog="catalog"
      :roles="roles"
      :saving="saving"
      :owner-locked-hint="t('admin.rolePermissions.ownerLockedHint', { role: lockedLabel })"
      @save="handleSave"
    />
  </div>
</template>
