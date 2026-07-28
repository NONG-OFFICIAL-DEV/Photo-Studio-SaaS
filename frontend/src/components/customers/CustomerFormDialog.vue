<script setup>
import { computed, ref, watch } from 'vue'
import { Field } from 'vee-validate'
import AppDialog from '@/components/common/AppDialog.vue'
import AppForm from '@/components/common/AppForm.vue'
import AppDatePicker from '@/components/common/AppDatePicker.vue'
import { customerSchema } from '@/utils/validators'
import { createCustomerApi, updateCustomerApi } from '@/apis/customer.api'
import { useCustomerTagsStore } from '@/stores/customerTags'
import { useAppStore } from '@/stores/app'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  customer: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const appStore = useAppStore()
const tagsStore = useCustomerTagsStore()

const loading = ref(false)
const errorMessage = ref('')

const isEdit = computed(() => Boolean(props.customer?.id))
const title = computed(() => (isEdit.value ? 'Edit Customer' : 'Add Customer'))

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
      appStore.notify('Customer updated successfully.')
    } else {
      await createCustomerApi(values)
      appStore.notify('Customer created successfully.')
    }
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to save customer.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AppDialog :model-value="modelValue" :title="title" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-alert v-if="errorMessage" type="error" variant="tonal" class="mb-4">{{ errorMessage }}</v-alert>

    <AppForm :schema="customerSchema" :initial-values="initialValues" @submit="onSubmit">
      <template #default="{ errors, values, setFieldValue }">
        <v-row>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="name">
              <v-text-field v-bind="field" label="Name *" :error-messages="errors.name" />
            </Field>
          </v-col>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="phone">
              <v-text-field v-bind="field" label="Phone" :error-messages="errors.phone" />
            </Field>
          </v-col>
          <v-col cols="12" sm="6">
            <Field v-slot="{ field }" name="email">
              <v-text-field v-bind="field" label="Email" type="email" :error-messages="errors.email" />
            </Field>
          </v-col>
          <v-col cols="12" sm="6">
            <v-select
              :model-value="values.gender"
              label="Gender"
              clearable
              :items="[{ title: 'Male', value: 'male' }, { title: 'Female', value: 'female' }, { title: 'Other', value: 'other' }]"
              @update:model-value="setFieldValue('gender', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <AppDatePicker
              :model-value="values.birthday"
              label="Birthday"
              :error-messages="errors.birthday"
              @update:model-value="setFieldValue('birthday', $event)"
            />
          </v-col>
          <v-col cols="12" sm="6">
            <v-autocomplete
              :model-value="values.tag_ids"
              label="Tags"
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
            <Field v-slot="{ field }" name="address">
              <v-textarea v-bind="field" label="Address" rows="2" :error-messages="errors.address" />
            </Field>
          </v-col>
        </v-row>

        <div class="d-flex justify-end ga-2 mt-2">
          <v-btn variant="text" :disabled="loading" @click="emit('update:modelValue', false)">Cancel</v-btn>
          <v-btn type="submit" color="primary" variant="flat" :loading="loading">Save</v-btn>
        </div>
      </template>
    </AppForm>
  </AppDialog>
</template>
