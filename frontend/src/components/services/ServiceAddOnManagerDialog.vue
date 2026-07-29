<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppConfirmDialog from '@/components/common/AppConfirmDialog.vue'
import { serviceAddOnSchema } from '@/utils/validators'
import { getServiceAddOnsApi, createServiceAddOnApi, deleteServiceAddOnApi } from '@/apis/service-addon.api'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const appStore = useAppStore()
const addOns = ref([])
const loading = ref(false)
const confirmDelete = ref(false)
const addOnToDelete = ref(null)

async function loadAddOns() {
  const { data } = await getServiceAddOnsApi()
  addOns.value = data.data
}

watch(() => props.modelValue, (open) => {
  if (open) loadAddOns()
})

async function onSubmit(values, { resetForm }) {
  loading.value = true
  try {
    await createServiceAddOnApi(values)
    resetForm()
    await loadAddOns()
    appStore.notify(t('services.messages.addOnCreated'))
  } catch (error) {
    appStore.notify(error.response?.data?.message || t('services.messages.addOnCreateError'), 'error')
  } finally {
    loading.value = false
  }
}

function askDelete(addOn) {
  addOnToDelete.value = addOn
  confirmDelete.value = true
}

async function confirmDeleteAddOn() {
  await deleteServiceAddOnApi(addOnToDelete.value.id)
  confirmDelete.value = false
  await loadAddOns()
  appStore.notify(t('services.messages.addOnDeleted'))
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="t('services.manageAddOns')" max-width="480" @update:model-value="emit('update:modelValue', $event)">
    <AppForm :schema="serviceAddOnSchema" :initial-values="{ name: '', price: null }" @submit="onSubmit">
      <template #default="{ errors }">
        <div class="d-flex ga-2 align-start mb-4">
          <Field v-slot="{ field }" name="name">
            <v-text-field v-bind="field" :label="t('services.addOnName')" density="compact" hide-details :error-messages="errors.name" />
          </Field>
          <Field v-slot="{ field }" name="price">
            <v-text-field v-bind="field" :label="t('fields.price')" type="number" step="0.01" prefix="$" density="compact" hide-details style="max-width: 120px" :error-messages="errors.price" />
          </Field>
          <v-btn type="submit" icon="mdi-plus" color="primary" :loading="loading" />
        </div>
      </template>
    </AppForm>

    <v-list density="compact">
      <v-list-item v-for="addOn in addOns" :key="addOn.id" :title="addOn.name" :subtitle="`$${addOn.price}`">
        <template #append>
          <v-btn icon="mdi-delete-outline" size="small" variant="text" @click="askDelete(addOn)" />
        </template>
      </v-list-item>
    </v-list>

    <AppConfirmDialog
      v-model="confirmDelete"
      :title="t('services.deleteAddOnTitle')"
      :message="t('services.deleteAddOnMessage', { name: addOnToDelete?.name })"
      @confirm="confirmDeleteAddOn"
    />
  </AppDialog>
</template>
