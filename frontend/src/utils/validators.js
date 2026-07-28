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

export const bookingSchema = yup.object({
  customer_id: yup.string().required('Customer is required'),
  assigned_user_id: yup.string().nullable(),
  type: yup.string().required('Type is required'),
  title: yup.string().nullable().max(255),
  notes: yup.string().nullable().max(2000),
  location_type: yup.string().required().oneOf(['studio', 'on_location']),
  location_address: yup.string().nullable().when('location_type', {
    is: 'on_location',
    then: schema => schema.required('Address is required for on-location bookings').max(1000),
  }),
  starts_at: yup.string().required('Start date/time is required'),
  ends_at: yup.string().required('End date/time is required')
    .test('after-start', 'End must be after start', function (value) {
      return !value || !this.parent.starts_at || new Date(value) > new Date(this.parent.starts_at)
    }),
})

export const cancelBookingSchema = yup.object({
  reason: yup.string().required().max(1000),
})
