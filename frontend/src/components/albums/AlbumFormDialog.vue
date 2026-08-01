<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { albumSchema } from '@/utils/validators'
import { createAlbumApi, updateAlbumApi } from '@/apis/album.api'
import { getCustomersApi } from '@/apis/customer.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  album: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()
const customerOptions = ref([])
const customerSearchLoading = ref(false)
// Selecting a customer re-fires @update:search with that customer's own
// name (Vuetify syncing the input box's visible text) — treating it as a
// fresh query narrows customerOptions down to just search matches for that
// name, so reopening the dropdown to pick someone else can show far fewer
// options than expected. Tracking the name just selected lets
// searchCustomers ignore that one echoed call.
const lastSelectedCustomerName = ref(null)

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
  if (term === lastSelectedCustomerName.value) return
  if (term.length < 2) return

  customerSearchLoading.value = true
  try {
    const { data } = await getCustomersApi({ search: term, perPage: 20 })
    customerOptions.value = data.data
  } finally {
    customerSearchLoading.value = false
  }
}

function selectCustomer(customerId, setFieldValue) {
  setFieldValue('customer_id', customerId)
  lastSelectedCustomerName.value = customerOptions.value.find((c) => c.id === customerId)?.name ?? null
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
    errorMessage.value = translateApiMessage(error, 'albums.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="albumSchema" :initial-values="initialValues" @submit="onSubmit">
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
              @update:model-value="selectCustomer($event, setFieldValue)"
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
      </template>
    </AppForm>

    <template #actions>
      <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">{{ t('common.cancel') }}</v-btn>
      <v-btn type="submit" :form="formId" color="primary" variant="flat" :loading="loading">{{ t('common.save') }}</v-btn>
    </template>
  </AppDialog>
</template>
