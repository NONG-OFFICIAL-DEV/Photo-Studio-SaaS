<script setup>
import { ref, watch } from 'vue'
import { Form as VeeForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'

/*
 * Thin wrapper around vee-validate's <Form>, wired to a yup schema.
 * Usage:
 *   <AppForm :schema="loginSchema" :initial-values="{ email: '' }" @submit="onSubmit">
 *     <template #default="{ errors }"> ... fields ... </template>
 *   </AppForm>
 */
defineProps({
  schema: { type: Object, required: true },
  initialValues: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submit'])

// yup error messages are resolved (and translated) at the moment a field is
// validated, so switching locale doesn't retranslate errors already on
// screen until something re-triggers validation for that field.
const { locale } = useI18n({ useScope: 'global' })
const formRef = ref(null)

watch(locale, () => {
  Object.keys(formRef.value?.errors || {}).forEach(field => formRef.value.validateField(field))
})
</script>

<template>
  <VeeForm
    ref="formRef"
    v-slot="slotProps"
    :validation-schema="schema"
    :initial-values="initialValues"
    @submit="(values, actions) => emit('submit', values, actions)"
  >
    <slot v-bind="slotProps" />
  </VeeForm>
</template>
