<script setup>
import { Form as VeeForm } from 'vee-validate'

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
</script>

<template>
  <VeeForm
    v-slot="slotProps"
    :validation-schema="schema"
    :initial-values="initialValues"
    @submit="(values, actions) => emit('submit', values, actions)"
  >
    <slot v-bind="slotProps" />
  </VeeForm>
</template>
