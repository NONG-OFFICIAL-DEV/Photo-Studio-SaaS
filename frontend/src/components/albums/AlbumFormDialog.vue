<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { albumSchema } from '@/utils/validators'
import { createAlbumApi, updateAlbumApi } from '@/apis/album.api'
import { getCustomersApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  album: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const customerOptions = ref([])
const customerSearchLoading = ref(false)

const isEdit = computed(() => Boolean(props.album?.id))
const title = computed(() => (isEdit.value ? t('albums.editAlbum') : t('albums.newAlbum')))

const initialValues = computed(() => ({
  name: props.album?.name ?? '',
  customer_id: props.album?.customer?.id ?? null,
  description: props.album?.description ?? '',
  expected_photo_count: props.album?.expected_photo_count ?? null,
}))

watch(() => props.modelValue, (open) => {
  if (open) {
    errorMessage.value = ''
    loadInitialCustomers()
  }
})

async function loadInitialCustomers() {
  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function searchCustomers(term) {
  if (!term) return loadInitialCustomers()
  if (term.length < 2) return

  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ search: term, perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateAlbumApi(props.album.id, values)
      appStore.notify(t('albums.messages.updatedSuccess'))
    } else {
      await createAlbumApi(values)
      appStore.notify(t('albums.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || t('albums.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="albumSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-autocomplete
              :model-value="values.customer_id"
              :label="t('fields.customer')"
              clearable
              item-title="name"
              item-value="id"
              :items="customerOptions"
              :loading="customerSearchLoading"
              no-filter
              @update:search="searchCustomers"
              @update:model-value="setFieldValue('customer_id', $event)"
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item v-bind="itemProps" :subtitle="item.raw.phone" />
              </template>
            </v-autocomplete>
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.expected_photo_count" :label="t('albums.expectedPhotoCount')" type="number" :error-messages="errors.expected_photo_count" @update:model-value="setFieldValue('expected_photo_count', $event)" />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.description" :label="t('fields.description')" rows="3" :error-messages="errors.description" @update:model-value="setFieldValue('description', $event)" />
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
