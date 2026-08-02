<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import RolePermissionMatrix from '@/components/common/RolePermissionMatrix.vue'
import { getAdminTenantRolePermissionsApi, updateAdminTenantRolePermissionsApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tenant: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const saving = ref(false)
const catalog = ref({})
const roles = ref([])
const matrixRef = ref(null)

watch(() => props.modelValue, async (open) => {
  if (!open || !props.tenant) return

  loading.value = true
  try {
    const { data } = await getAdminTenantRolePermissionsApi(props.tenant.id)
    catalog.value = data.data.catalog
    roles.value = data.data.roles
  } finally {
    loading.value = false
  }
})

const lockedLabel = computed(() => roles.value.find((r) => r.locked)?.label ?? 'Owner')

async function handleSave(role, permissions) {
  saving.value = true
  try {
    await updateAdminTenantRolePermissionsApi(props.tenant.id, role, permissions)
    matrixRef.value?.markSaved(role)
    appStore.notify(t('admin.tenantRolePermissions.messages.savedSuccess'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.tenantRolePermissions.messages.saveError'), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('admin.tenantRolePermissions.dialogTitle', { name: tenant?.name })"
    max-width="900"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div v-if="loading" class="d-flex justify-center py-8">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <RolePermissionMatrix
      v-else
      ref="matrixRef"
      :catalog="catalog"
      :roles="roles"
      :saving="saving"
      :owner-locked-hint="t('admin.tenantRolePermissions.ownerLockedHint', { role: lockedLabel })"
      @save="handleSave"
    />
  </AppDialog>
</template>
