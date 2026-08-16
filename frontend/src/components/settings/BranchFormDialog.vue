<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import { branchSchema } from '@/utils/validators'
import { createBranchApi, updateBranchApi } from '@/apis/branch.api'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  branch: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const isEdit = computed(() => Boolean(props.branch?.id))
const title = computed(() => (isEdit.value ? t('branches.editBranch') : t('branches.newBranch')))

const initialValues = computed(() => ({
  name: props.branch?.name ?? '',
  address: props.branch?.address ?? '',
  phone: props.branch?.phone ?? '',
  is_active: props.branch?.is_active ?? true,
}))

watch(() => props.modelValue, (open) => {
  if (open) errorMessage.value = ''
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateBranchApi(props.branch.id, values)
      appStore.notify(t('branches.messages.updatedSuccess'))
    } else {
      await createBranchApi(values)
      appStore.notify(t('branches.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'branches.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="560" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="branchSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.address" :label="t('branches.fields.address')" rows="2" :error-messages="errors.address" @update:model-value="setFieldValue('address', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.phone" :label="t('branches.fields.phone')" :error-messages="errors.phone" @update:model-value="setFieldValue('phone', $event)" />
          </v-col>
          <v-col cols="12" sm="6" class="d-flex align-center">
            <v-switch
              :model-value="values.is_active"
              :label="t('services.activeSwitchLabel')"
              color="primary"
              hide-details
              @update:model-value="setFieldValue('is_active', $event)"
            />
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
