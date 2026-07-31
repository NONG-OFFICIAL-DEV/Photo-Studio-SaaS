<script setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { customerSchema } from '@/utils/validators'
import { createCustomerApi, updateCustomerApi } from '@/apis/customer.api'
import { useCustomerTagsStore } from '@/stores/customerTags'
import { useAppStore } from '@/stores/app'
import { translateApiMessage } from '@/utils/apiMessages'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  customer: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const appStore = useAppStore()
const tagsStore = useCustomerTagsStore()

const loading = ref(false)
const errorMessage = ref('')
const formId = useId()

const isEdit = computed(() => Boolean(props.customer?.id))
const title = computed(() => (isEdit.value ? t('customers.dialogs.editTitle') : t('customers.actions.addCustomer')))

const initialValues = computed(() => ({
  name: props.customer?.name ?? '',
  email: props.customer?.email ?? '',
  phone: props.customer?.phone ?? '',
  address: props.customer?.address ?? '',
  birthday: props.customer?.birthday ?? null,
  gender: props.customer?.gender ?? null,
  tag_ids: props.customer?.tags?.map((t) => t.id) ?? [],
}))

watch(() => props.modelValue, (open) => {
  if (open) tagsStore.fetch()
})

async function onSubmit(values) {
  loading.value = true
  errorMessage.value = ''

  try {
    if (isEdit.value) {
      await updateCustomerApi(props.customer.id, values)
      appStore.notify(t('customers.messages.updatedSuccess'))
    } else {
      await createCustomerApi(values)
      appStore.notify(t('customers.messages.createdSuccess'))
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = translateApiMessage(error, 'customers.messages.saveError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :id="formId" :schema="customerSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.name" :label="`${t('fields.name')} *`" :error-messages="errors.name" @update:model-value="setFieldValue('name', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.phone" :label="t('fields.phone')" :error-messages="errors.phone" @update:model-value="setFieldValue('phone', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field :model-value="values.email" :label="t('fields.email')" type="email" :error-messages="errors.email" @update:model-value="setFieldValue('email', $event)" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.gender"
              :label="t('fields.gender')"
              clearable
              :items="[
                { title: t('customers.genderOptions.male'), value: 'male' },
                { title: t('customers.genderOptions.female'), value: 'female' },
                { title: t('customers.genderOptions.other'), value: 'other' },
              ]"
              @update:model-value="setFieldValue('gender', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <AppDatePicker
              :model-value="values.birthday"
              :label="t('fields.birthday')"
              :error-messages="errors.birthday"
              @update:model-value="setFieldValue('birthday', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-autocomplete
              :model-value="values.tag_ids"
              :label="t('fields.tags')"
              multiple
              chips
              closable-chips
              item-title="name"
              item-value="id"
              :items="tagsStore.tags"
              @update:model-value="setFieldValue('tag_ids', $event)"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea :model-value="values.address" :label="t('fields.address')" rows="2" :error-messages="errors.address" @update:model-value="setFieldValue('address', $event)" />
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
