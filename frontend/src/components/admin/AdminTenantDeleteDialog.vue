<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import { deleteAdminTenantApi } from '@/apis/admin.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  tenant: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'deleted'])

const { t } = useI18n()
const appStore = useAppStore()

const confirmName = ref('')
const deleting = ref(false)

watch(() => props.modelValue, (open) => {
  if (open) confirmName.value = ''
})

const isMatch = computed(() => Boolean(props.tenant) && confirmName.value === props.tenant.name)

async function handleDelete() {
  if (!isMatch.value) return

  deleting.value = true
  try {
    await deleteAdminTenantApi(props.tenant.id, confirmName.value)
    appStore.notify(t('admin.tenantDelete.messages.success', { name: props.tenant.name }))
    emit('update:modelValue', false)
    emit('deleted')
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'admin.tenantDelete.messages.error'), 'error')
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('admin.tenantDelete.dialogTitle', { name: tenant?.name })"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <v-alert type="error" variant="tonal" density="comfortable" class="mb-4">
      {{ t('admin.tenantDelete.warning') }}
    </v-alert>

    <ul class="text-body-2 text-medium-emphasis mb-4 pl-5">
      <li>{{ t('admin.tenantDelete.impact.users') }}</li>
      <li>{{ t('admin.tenantDelete.impact.customers') }}</li>
      <li>{{ t('admin.tenantDelete.impact.records') }}</li>
      <li>{{ t('admin.tenantDelete.impact.files') }}</li>
    </ul>

    <p class="text-body-2 mb-2">{{ t('admin.tenantDelete.confirmHint', { name: tenant?.name }) }}</p>

    <v-text-field
      v-model="confirmName"
      :placeholder="tenant?.name"
      variant="outlined"
      density="comfortable"
      autofocus
      @keyup.enter="handleDelete"
    />

    <template #actions>
      <v-btn variant="text" @click="emit('update:modelValue', false)">
        {{ t('admin.tenantDelete.cancelButton') }}
      </v-btn>
      <v-btn
        color="error"
        variant="flat"
        :disabled="!isMatch"
        :loading="deleting"
        @click="handleDelete"
      >
        {{ t('admin.tenantDelete.confirmButton') }}
      </v-btn>
    </template>
  </AppDialog>
</template>
