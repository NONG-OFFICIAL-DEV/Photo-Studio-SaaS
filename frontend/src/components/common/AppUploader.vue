<script setup>
import { ref, computed } from 'vue'

/*
 * Base drag-and-drop file picker. Emits raw File objects — actual chunked
 * upload to storage (Laravel Storage / S3) is wired per-module (Gallery,
 * Documents, ...) via a dedicated upload service, since chunk size and
 * destination differ per use case.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: true },
  accept: { type: String, default: 'image/*' },
  maxSizeMb: { type: Number, default: 25 },
})

const emit = defineEmits(['update:modelValue', 'error'])

const isDragging = ref(false)
const inputRef = ref(null)

const previews = computed(() =>
  props.modelValue.map((file) => ({
    name: file.name,
    size: file.size,
    url: file.type?.startsWith('image/') ? URL.createObjectURL(file) : null,
  })),
)

function openPicker() {
  inputRef.value?.click()
}

function handleFiles(fileList) {
  const files = Array.from(fileList)
  const tooBig = files.filter((f) => f.size > props.maxSizeMb * 1024 * 1024)

  if (tooBig.length) {
    emit('error', `${tooBig.length} file(s) exceed ${props.maxSizeMb}MB and were skipped.`)
  }

  const accepted = files.filter((f) => f.size <= props.maxSizeMb * 1024 * 1024)
  emit('update:modelValue', props.multiple ? [...props.modelValue, ...accepted] : accepted.slice(0, 1))
}

function onDrop(event) {
  isDragging.value = false
  handleFiles(event.dataTransfer.files)
}

function onInputChange(event) {
  handleFiles(event.target.files)
  event.target.value = ''
}

function removeFile(index) {
  const next = [...props.modelValue]
  next.splice(index, 1)
  emit('update:modelValue', next)
}
</script>

<template>
  <div>
    <div
      class="app-uploader"
      :class="{ 'app-uploader--dragging': isDragging }"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="openPicker"
    >
      <input
        ref="inputRef"
        type="file"
        class="d-none"
        :multiple="multiple"
        :accept="accept"
        @change="onInputChange"
      >
      <v-icon icon="mdi-cloud-upload-outline" size="40" color="primary" class="mb-2" />
      <div class="text-body-2 text-medium-emphasis">
        Drag & drop files here, or click to browse
      </div>
    </div>

    <v-row v-if="previews.length" class="mt-2" dense>
      <v-col v-for="(preview, index) in previews" :key="index" cols="6" sm="4" md="3">
        <v-card variant="outlined" class="pa-1">
          <v-img v-if="preview.url" :src="preview.url" height="90" cover />
          <div v-else class="d-flex align-center justify-center" style="height: 90px">
            <v-icon icon="mdi-file-outline" size="32" />
          </div>
          <div class="d-flex align-center justify-space-between px-1">
            <span class="text-caption text-truncate">{{ preview.name }}</span>
            <v-btn icon="mdi-close" size="x-small" variant="text" @click.stop="removeFile(index)" />
          </div>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.app-uploader {
  border: 2px dashed rgba(var(--v-theme-primary), 0.4);
  border-radius: 12px;
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.app-uploader--dragging {
  background-color: rgba(var(--v-theme-primary), 0.08);
}
</style>
