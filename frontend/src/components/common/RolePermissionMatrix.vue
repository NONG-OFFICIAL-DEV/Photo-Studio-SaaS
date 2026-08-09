<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'

const props = defineProps({
  catalog: { type: Object, required: true },
  roles: { type: Array, required: true },
  saving: { type: Boolean, default: false },
  ownerLockedHint: { type: String, required: true },
})

const emit = defineEmits(['save'])

const { t } = useI18n()

const activeRole = ref(null)
// Snapshot of what's actually saved per role, separate from the live
// checkbox state — lets the Save button know whether THIS tab has
// unsaved changes without re-fetching or diffing against the server.
const savedPermissions = ref({})
const editedPermissions = ref({})

watch(
  () => props.roles,
  (roles) => {
    const editable = roles.filter((r) => !r.locked)
    for (const role of editable) {
      savedPermissions.value[role.role] = [...role.permissions]
      editedPermissions.value[role.role] = [...role.permissions]
    }
    if (!activeRole.value) activeRole.value = editable[0]?.role ?? null
  },
  { immediate: true },
)

const editableRoles = computed(() => props.roles.filter((r) => !r.locked))
const modules = computed(() => Object.entries(props.catalog))

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

function save() {
  emit('save', activeRole.value, editedPermissions.value[activeRole.value])
}

function markSaved(role) {
  savedPermissions.value[role] = [...editedPermissions.value[role]]
}

function discard() {
  editedPermissions.value[activeRole.value] = [...(savedPermissions.value[activeRole.value] ?? [])]
}

// The plain Cancel button used to call discard() directly — a stray
// click silently threw away every unchecked/checked box on this tab
// with no way back. Now it opens this confirmation first.
const discardConfirmOpen = ref(false)

function confirmDiscard() {
  discard()
  discardConfirmOpen.value = false
}

defineExpose({ markSaved })
</script>

<template>
  <div>
    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
      {{ ownerLockedHint }}
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
        <v-btn variant="text" :disabled="!isDirty || saving" @click="discardConfirmOpen = true">
          {{ t('common.cancel') }}
        </v-btn>
        <v-btn color="primary" variant="flat" :disabled="!isDirty" :loading="saving" @click="save">
          {{ t('common.save') }}
        </v-btn>
      </div>
    </v-card>

    <AppConfirmDialog
      v-model="discardConfirmOpen"
      color="error"
      :title="t('common.discardChanges')"
      :message="t('common.discardChangesMessage')"
      @confirm="confirmDiscard"
    />
  </div>
</template>
