import * as yup from 'yup'

const passwordRule = yup
  .string()
  .required()
  .min(8)
  .matches(/[a-z]/, 'Must contain a lowercase letter')
  .matches(/[A-Z]/, 'Must contain an uppercase letter')
  .matches(/\d/, 'Must contain a number')

export const loginSchema = yup.object({
  email: yup.string().required().email(),
  password: yup.string().required(),
  remember: yup.boolean(),
})

export const registerSchema = yup.object({
  studio_name: yup.string().required().max(255),
  owner_name: yup.string().required().max(255),
  email: yup.string().required().email(),
  phone: yup.string().nullable(),
  password: passwordRule,
  password_confirmation: yup
    .string()
    .required()
    .oneOf([yup.ref('password')], 'Passwords must match'),
})

export const forgotPasswordSchema = yup.object({
  email: yup.string().required().email(),
})

export const resetPasswordSchema = yup.object({
  password: passwordRule,
  password_confirmation: yup
    .string()
    .required()
    .oneOf([yup.ref('password')], 'Passwords must match'),
})
