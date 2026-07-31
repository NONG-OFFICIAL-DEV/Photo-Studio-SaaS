<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppUploader from '@/components/common/AppUploader.vue'
import { importCustomersApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'imported'])

const { t } = useI18n()
const appStore = useAppStore()
const files = ref([])
const loading = ref(false)
const result = ref(null)

async function runImport() {
  if (!files.value.length) return

  loading.value = true
  result.value = null

  try {
    const { data } = await importCustomersApi(files.value[0])
    result.value = data.data
    if (result.value.imported > 0) {
      emit('imported')
    }
  } catch (error) {
    appStore.notify(translateApiMessage(error, 'customers.messages.importFailed'), 'error')
  } finally {
    loading.value = false
  }
}

function close() {
  files.value = []
  result.value = null
  emit('update:modelValue', false)
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('customers.dialogs.importTitle')" max-width="560" @update:model-value="close">
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ t('customers.dialogs.importInstructions') }}
    </p>

    <AppUploader v-model="files" :multiple="false" accept=".csv,.xlsx,.xls" @error="(msg) => appStore.notify(msg, 'error')" />

    <div v-if="result" class="mt-4">
      <v-alert :type="result.failed ? 'warning' : 'success'" variant="tonal">
        {{ t('customers.import.resultSummary', { imported: result.imported, failed: result.failed }) }}
      </v-alert>

      <v-list v-if="result.failures?.length" density="compact" class="mt-2">
        <v-list-item v-for="(failure, index) in result.failures" :key="index">
          <v-list-item-title>{{ t('customers.import.rowError', { row: failure.row, errors: failure.errors.join(', ') }) }}</v-list-item-title>
        </v-list-item>
      </v-list>
    </div>

    <template #actions>
      <v-btn variant="text" @click="close">{{ t('common.close') }}</v-btn>
      <v-btn color="primary" variant="flat" :loading="loading" :disabled="!files.length" @click="runImport">
        {{ t('customers.actions.import') }}
      </v-btn>
    </template>
  </AppDialog>
</template>
