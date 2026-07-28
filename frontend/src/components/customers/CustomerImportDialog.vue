<script setup>
import { ref } from 'vue'
import AppDialog from '@/components/common/AppDialog.vue'
import AppUploader from '@/components/common/AppUploader.vue'
import { importCustomersApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'

defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'imported'])

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
    appStore.notify(error.response?.data?.message || 'Import failed.', 'error')
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
  <AppDialog :model-value="modelValue" title="Import Customers" max-width="560" @update:model-value="close">
    <p class="text-body-2 text-medium-emphasis mb-4">
      Upload a CSV or Excel file with columns: name, email, phone, address, birthday, gender.
      Rows with a missing name are skipped and reported below.
    </p>

    <AppUploader v-model="files" :multiple="false" accept=".csv,.xlsx,.xls" @error="(msg) => appStore.notify(msg, 'error')" />

    <div v-if="result" class="mt-4">
      <v-alert :type="result.failed ? 'warning' : 'success'" variant="tonal">
        {{ result.imported }} imported, {{ result.failed }} failed.
      </v-alert>

      <v-list v-if="result.failures?.length" density="compact" class="mt-2">
        <v-list-item v-for="(failure, index) in result.failures" :key="index">
          <v-list-item-title>Row {{ failure.row }}: {{ failure.errors.join(', ') }}</v-list-item-title>
        </v-list-item>
      </v-list>
    </div>

    <div class="d-flex justify-end ga-2 mt-4">
      <v-btn variant="text" @click="close">Close</v-btn>
      <v-btn color="primary" variant="flat" :loading="loading" :disabled="!files.length" @click="runImport">
        Import
      </v-btn>
    </div>
  </AppDialog>
</template>
