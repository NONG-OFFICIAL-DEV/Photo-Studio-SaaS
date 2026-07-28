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

export const customerSchema = yup.object({
  name: yup.string().required().max(255),
  email: yup.string().nullable().email(),
  phone: yup.string().nullable().max(30),
  address: yup.string().nullable().max(1000),
  birthday: yup.string().nullable(),
  gender: yup.string().nullable().oneOf(['male', 'female', 'other', null, '']),
  tag_ids: yup.array().of(yup.string()),
})

export const customerTagSchema = yup.object({
  name: yup.string().required().max(100),
  color: yup.string().nullable(),
})

export const blacklistSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const noteSchema = yup.object({
  note: yup.string().required().max(2000),
})
