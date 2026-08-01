<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppApiErrorAlert from '@/components/common/AppApiErrorAlert.vue'
import { sendCustomerTelegramFilesApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  customerId: { type: String, default: null },
  customerName: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()

const fileInput = ref(null)
const files = ref([])
const caption = ref('')
const sending = ref(false)
const submitError = ref(null)

watch(() => props.modelValue, (open) => {
  if (open) {
    files.value = []
    caption.value = ''
    submitError.value = null
  }
})

function triggerFilePicker() {
  fileInput.value?.click()
}

function onFilesSelected(event) {
  files.value = Array.from(event.target.files || [])
}

function removeFile(index) {
  files.value = files.value.filter((_, i) => i !== index)
}

async function send() {
  if (!files.value.length) return

  sending.value = true
  submitError.value = null
  try {
    const { data } = await sendCustomerTelegramFilesApi(props.customerId, files.value, caption.value)
    appStore.notify(t('albums.telegram.sentSuccess', { count: data.data.sent }))
    emit('update:modelValue', false)
  } catch (error) {
    submitError.value = error
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <AppDialog
    :model-value="modelValue"
    :title="t('albums.telegram.dialogTitle', { name: customerName })"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <AppApiErrorAlert :error="submitError" fallback-key="albums.telegram.sendError" />

    <p class="text-body-2 text-medium-emphasis mb-4">{{ t('albums.telegram.hint') }}</p>

    <input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="d-none" @change="onFilesSelected" />
    <v-btn variant="outlined" prepend-icon="mdi-folder-open-outline" @click="triggerFilePicker">
      {{ t('albums.telegram.chooseFiles') }}
    </v-btn>

    <v-list v-if="files.length" density="compact" class="mt-3">
      <v-list-item v-for="(file, index) in files" :key="`${file.name}-${index}`">
        <v-list-item-title>{{ file.name }}</v-list-item-title>
        <template #append>
          <v-btn icon="mdi-close" size="small" variant="text" @click="removeFile(index)" />
        </template>
      </v-list-item>
    </v-list>

    <v-textarea
      v-model="caption"
      class="mt-3"
      rows="2"
      auto-grow
      :label="t('albums.telegram.captionLabel')"
      :placeholder="t('albums.telegram.captionPlaceholder')"
    />

    <template #actions>
      <v-btn variant="text" :disabled="sending" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn color="primary" variant="flat" :disabled="!files.length" :loading="sending" @click="send">
        {{ t('albums.telegram.send') }}
      </v-btn>
    </template>
  </AppDialog>
</template>
