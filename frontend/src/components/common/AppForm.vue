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
 *
 * The optional `id` prop exists for dialogs: AppDialog's #actions slot
 * renders inside v-card-actions, a sibling of v-card-text — not a
 * descendant of the <form> this component renders — so a submit button
 * placed there can't trigger submission just by being type="submit"
 * inside the form. Give this component an id and point the button at it
 * via the standard HTML `<button type="submit" form="...">` association
 * instead (see any *FormDialog.vue for the pattern).
 */
defineProps({
  schema: { type: Object, required: true },
  initialValues: { type: Object, default: () => ({}) },
  id: { type: String, default: undefined },
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
    :id="id"
    ref="formRef"
    v-slot="slotProps"
    :validation-schema="schema"
    :initial-values="initialValues"
    @submit="(values, actions) => emit('submit', values, actions)"
  >
    <slot v-bind="slotProps" />
  </VeeForm>
</template>
