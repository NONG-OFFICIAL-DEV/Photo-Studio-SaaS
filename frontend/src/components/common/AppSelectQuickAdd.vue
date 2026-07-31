<script setup>
import { nextTick, ref } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: null },
  items: { type: Array, default: () => [] },
  itemTitle: { type: String, default: 'name' },
  itemValue: { type: String, default: 'id' },
  label: { type: String, default: '' },
  clearable: { type: Boolean, default: true },
  errorMessages: { type: [String, Array], default: () => [] },
  addLabel: { type: String, required: true },
  namePlaceholder: { type: String, required: true },
  descriptionPlaceholder: { type: String, default: null },
  createFn: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue', 'created'])

const menu = ref(false)
const adding = ref(false)
const newName = ref('')
const newDescription = ref('')
const creating = ref(false)
const createError = ref('')
const nameField = ref(null)

function startAdding() {
  adding.value = true
  newName.value = ''
  newDescription.value = ''
  createError.value = ''
  nextTick(() => nameField.value?.focus())
}

function cancelAdding() {
  adding.value = false
  newName.value = ''
  newDescription.value = ''
  createError.value = ''
}

function onMenuChange(open) {
  menu.value = open
  if (!open) cancelAdding()
}

async function confirmAdd() {
  if (!newName.value.trim()) return

  creating.value = true
  createError.value = ''
  try {
    const created = await props.createFn({
      name: newName.value.trim(),
      description: props.descriptionPlaceholder ? (newDescription.value.trim() || null) : undefined,
    })
    emit('created', created)
    emit('update:modelValue', created[props.itemValue])
    menu.value = false
    cancelAdding()
  } catch (error) {
    createError.value = error?.message || ''
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <v-select
    :menu="menu"
    :model-value="modelValue"
    :label="label"
    :items="items"
    :item-title="itemTitle"
    :item-value="itemValue"
    :clearable="clearable"
    :error-messages="errorMessages"
    @update:model-value="emit('update:modelValue', $event)"
    @update:menu="onMenuChange"
  >
    <template #append-item>
      <v-divider class="my-1" />
      <v-list-item v-if="!adding" density="compact" prepend-icon="mdi-plus" :title="addLabel" @click.stop="startAdding" />
      <div v-else class="px-3 py-2" @click.stop @mousedown.stop>
        <div class="d-flex ga-2 align-start">
          <div class="flex-grow-1 d-flex flex-column ga-2">
            <v-text-field
              ref="nameField"
              v-model="newName"
              :placeholder="namePlaceholder"
              density="compact"
              hide-details="auto"
              :error-messages="createError"
              :disabled="creating"
              @keyup.enter="confirmAdd"
              @keyup.esc="cancelAdding"
            />
            <v-textarea
              v-if="descriptionPlaceholder"
              v-model="newDescription"
              :placeholder="descriptionPlaceholder"
              density="compact"
              rows="2"
              hide-details
              :disabled="creating"
              @keyup.esc="cancelAdding"
            />
          </div>
          <v-btn icon="mdi-check" size="small" color="primary" :loading="creating" @click="confirmAdd" />
          <v-btn icon="mdi-close" size="small" variant="text" :disabled="creating" @click="cancelAdding" />
        </div>
      </div>
    </template>
  </v-select>
</template>
