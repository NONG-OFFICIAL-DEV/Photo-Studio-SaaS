<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppToolbar from '@/components/common/AppToolbar.vue'
import { getAdminRolePermissionsApi, updateAdminRolePermissionsApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(true)
const saving = ref(false)
const catalog = ref({})
const roles = ref([])
const activeRole = ref(null)
// Snapshot of what's actually saved per role, separate from the live
// checkbox state — lets the Save button know whether THIS tab has
// unsaved changes without re-fetching or diffing against the server.
const savedPermissions = ref({})
const editedPermissions = ref({})

async function load() {
  loading.value = true
  try {
    const { data } = await getAdminRolePermissionsApi()
    catalog.value = data.data.catalog
    roles.value = data.data.roles
    const editable = roles.value.filter((r) => !r.locked)
    for (const role of editable) {
      savedPermissions.value[role.role] = [...role.permissions]
      editedPermissions.value[role.role] = [...role.permissions]
    }
    activeRole.value = editable[0]?.role ?? null
  } finally {
    loading.value = false
  }
}

load()

const editableRoles = computed(() => roles.value.filter((r) => !r.locked))
const lockedRole = computed(() => roles.value.find((r) => r.locked))

const modules = computed(() => Object.entries(catalog.value))

function isChecked(permission) {
  return editedPermissions.value[activeRole.value]?.includes(permission) ?? false
}

function toggle(permission, checked) {
  const current = editedPermissions.value[activeRole.value] ?? []
  editedPermissions.value[activeRole.value] = checked
    ? [...current, permission]
    : current.filter((p) => p !== permission)
}

function toggleModule(modulePermissions, checked) {
  const current = new Set(editedPermissions.value[activeRole.value] ?? [])
  for (const permission of modulePermissions) {
    if (checked) current.add(permission)
    else current.delete(permission)
  }
  editedPermissions.value[activeRole.value] = [...current]
}

function moduleState(modulePermissions) {
  const current = editedPermissions.value[activeRole.value] ?? []
  const checkedCount = modulePermissions.filter((p) => current.includes(p)).length
  if (checkedCount === 0) return { checked: false, indeterminate: false }
  if (checkedCount === modulePermissions.length) return { checked: true, indeterminate: false }
  return { checked: false, indeterminate: true }
}

function humanize(segment) {
  return segment.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

function permissionLabel(slug) {
  return slug.split('.').map(humanize).join(' — ')
}

const isDirty = computed(() => {
  if (!activeRole.value) return false
  const saved = [...(savedPermissions.value[activeRole.value] ?? [])].sort()
  const edited = [...(editedPermissions.value[activeRole.value] ?? [])].sort()
  return JSON.stringify(saved) !== JSON.stringify(edited)
})

async function save() {
  saving.value = true
  try {
    await updateAdminRolePermissionsApi(activeRole.value, editedPermissions.value[activeRole.value])
    savedPermissions.value[activeRole.value] = [...editedPermissions.value[activeRole.value]]
    appStore.notify(t('admin.rolePermissions.messages.savedSuccess'))
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.rolePermissions.messages.saveError'), 'error')
  } finally {
    saving.value = false
  }
}

function discard() {
  editedPermissions.value[activeRole.value] = [...(savedPermissions.value[activeRole.value] ?? [])]
}
</script>

<template>
  <div>
    <AppToolbar :title="t('admin.rolePermissions.title')" :subtitle="t('admin.rolePermissions.subtitle')" />

    <v-skeleton-loader v-if="loading" type="article" />

    <template v-else>
      <v-alert type="info" variant="tonal" density="compact" class="mb-4">
        {{ t('admin.rolePermissions.ownerLockedHint', { role: lockedRole?.label }) }}
      </v-alert>

      <v-tabs v-model="activeRole" class="mb-4">
        <v-tab v-for="role in editableRoles" :key="role.role" :value="role.role">{{ role.label }}</v-tab>
      </v-tabs>

      <v-card variant="flat" border rounded="lg" class="pa-4">
        <div v-for="[moduleName, permissions] in modules" :key="moduleName" class="mb-4">
          <v-checkbox
            :model-value="moduleState(permissions).checked"
            :indeterminate="moduleState(permissions).indeterminate"
            :label="humanize(moduleName)"
            density="compact"
            hide-details
            class="font-weight-bold"
            @update:model-value="toggleModule(permissions, $event)"
          />
          <div class="d-flex flex-wrap ga-2 ml-8">
            <v-checkbox
              v-for="permission in permissions"
              :key="permission"
              :model-value="isChecked(permission)"
              :label="permissionLabel(permission)"
              density="compact"
              hide-details
              style="min-width: 240px"
              @update:model-value="toggle(permission, $event)"
            />
          </div>
        </div>

        <v-divider class="mb-4" />

        <div class="d-flex justify-end ga-2">
          <v-btn variant="text" :disabled="!isDirty || saving" @click="discard">{{ t('common.cancel') }}</v-btn>
          <v-btn color="primary" variant="flat" :disabled="!isDirty" :loading="saving" @click="save">
            {{ t('common.save') }}
          </v-btn>
        </div>
      </v-card>
    </template>
  </div>
</template>
