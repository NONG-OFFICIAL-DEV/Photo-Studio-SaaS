import * as yup from 'yup'
import i18n from '@/plugins/i18n'

const { t } = i18n.global

yup.setLocale({
  mixed: {
    required: () => t('validation.required'),
  },
  string: {
    email: () => t('validation.email'),
    min: ({ min }) => t('validation.minLength', { min }),
    max: ({ max }) => t('validation.maxLength', { max }),
  },
})

const passwordRule = yup
  .string()
  .required()
  .min(8)
  .matches(/[a-z]/, () => t('validation.passwordStrength'))
  .matches(/[A-Z]/, () => t('validation.passwordStrength'))
  .matches(/\d/, () => t('validation.passwordStrength'))

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
    .oneOf([yup.ref('password')], () => t('validation.passwordMatch')),
})

export const forgotPasswordSchema = yup.object({
  email: yup.string().required().email(),
})

export const resetPasswordSchema = yup.object({
  password: passwordRule,
  password_confirmation: yup
    .string()
    .required()
    .oneOf([yup.ref('password')], () => t('validation.passwordMatch')),
})

export const updateEmailSchema = yup.object({
  current_password: yup.string().required(),
  email: yup.string().required().email(),
})

export const updatePasswordSchema = yup.object({
  current_password: yup.string().required(),
  password: passwordRule,
  password_confirmation: yup
    .string()
    .required()
    .oneOf([yup.ref('password')], () => t('validation.passwordMatch')),
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
  customer_id: yup.string().required(() => t('validation.customerRequired')),
  assigned_user_id: yup.string().nullable(),
  type: yup.string().required(() => t('validation.typeRequired')),
  title: yup.string().nullable().max(255),
  notes: yup.string().nullable().max(2000),
  location_type: yup.string().required().oneOf(['studio', 'on_location']),
  location_address: yup.string().nullable().when('location_type', {
    is: 'on_location',
    then: schema => schema.required(() => t('validation.addressRequiredOnLocation')).max(1000),
  }),
  starts_at: yup.string().required(() => t('validation.startDateTimeRequired')),
  ends_at: yup.string().required(() => t('validation.endDateTimeRequired'))
    .test('after-start', () => t('validation.endAfterStart'), function (value) {
      return !value || !this.parent.starts_at || new Date(value) > new Date(this.parent.starts_at)
    }),
})

export const cancelBookingSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const serviceSchema = yup.object({
  category_id: yup.string().nullable(),
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(2000),
  deliverables: yup.string().nullable().max(2000),
  price: yup.number().typeError(() => t('validation.priceNumber')).required().min(0),
  pricing_unit: yup.string().required().oneOf(['fixed', 'per_hour', 'per_person', 'per_photo']),
  duration_minutes: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(1),
  is_active: yup.boolean(),
})

export const serviceCategorySchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(1000),
})

export const serviceAddOnSchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(1000),
  price: yup.number().typeError(() => t('validation.priceNumber')).required().min(0),
})

export const orderSchema = yup.object({
  customer_id: yup.string().required(() => t('validation.customerRequired')),
  booking_id: yup.string().nullable(),
  discount_amount: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  notes: yup.string().nullable().max(2000),
})

export const cancelOrderSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const packageSchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(2000),
  discount_type: yup.string().nullable(),
  discount_value: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0)
    .when('discount_type', {
      is: 'percent',
      then: schema => schema.max(100, () => t('validation.percentDiscountMax')),
    }),
  override_price: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  is_active: yup.boolean(),
})

export const expenseCategorySchema = yup.object({
  name: yup.string().required().max(255),
})

export const attendanceSchema = yup.object({
  user_id: yup.string().required(() => t('validation.employeeRequired')),
  date: yup.string().required(() => t('validation.dateRequired')),
  status: yup.string().required(),
  reason: yup.string().nullable().max(1000),
})

export const commissionEntrySchema = yup.object({
  user_id: yup.string().required(() => t('validation.employeeRequired')),
  order_id: yup.string().nullable(),
  amount: yup.number().typeError(() => t('validation.mustBeNumber')).required().min(0.01),
  earned_date: yup.string().required(() => t('validation.dateRequired')),
  notes: yup.string().nullable().max(2000),
})

export const payrollEntrySchema = yup.object({
  user_id: yup.string().required(() => t('validation.employeeRequired')),
  period_label: yup.string().required().max(255),
  period_start: yup.string().required(() => t('validation.startDateRequired')),
  period_end: yup.string().required(() => t('validation.endDateRequired')),
  base_pay: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  commission_total: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  deductions: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  notes: yup.string().nullable().max(2000),
})

export const employmentSchema = yup.object({
  pay_type: yup.string().required(),
  base_pay: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  commission_rate: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0).max(100),
})

export const employeeSchema = yup.object({
  name: yup.string().required().max(255),
  email: yup.string().required().email(),
  phone: yup.string().nullable().max(30),
  password: passwordRule,
  role: yup.string().required(),
  pay_type: yup.string().required(),
  base_pay: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  commission_rate: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0).max(100),
})

export const expenseSchema = yup.object({
  category_id: yup.string().nullable(),
  amount: yup.number().typeError(() => t('validation.mustBeNumber')).required().min(0.01),
  expense_date: yup.string().required(() => t('validation.expenseDateRequired')),
  vendor: yup.string().nullable().max(255),
  payment_method: yup.string().required(),
  notes: yup.string().nullable().max(2000),
})

export const inventoryItemSchema = yup.object({
  name: yup.string().required().max(255),
  sku: yup.string().nullable().max(100),
  unit: yup.string().required().max(50),
  category: yup.string().nullable().max(100),
  reorder_threshold: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  is_active: yup.boolean(),
  initial_quantity: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
})

export const inventoryMovementSchema = yup.object({
  type: yup.string().required(),
  quantity: yup.number().typeError(() => t('validation.mustBeNumber')).required().min(0.01),
  reason: yup.string().nullable().max(1000),
  moved_at: yup.string().nullable(),
})

export const albumSchema = yup.object({
  name: yup.string().required().max(255),
  customer_id: yup.string().nullable(),
  order_id: yup.string().nullable(),
  description: yup.string().nullable().max(2000),
  expected_photo_count: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
})

export const invoiceSchema = yup.object({
  customer_id: yup.string().nullable().when('order_id', {
    is: val => !val,
    then: schema => schema.required(() => t('validation.customerRequiredUnlessOrder')),
  }),
  order_id: yup.string().nullable(),
  issue_date: yup.string().nullable(),
  due_date: yup.string().nullable(),
  discount_amount: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  tax_rate: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0).max(100),
  notes: yup.string().nullable().max(2000),
})

export const voidInvoiceSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const paymentSchema = yup.object({
  amount: yup.number().typeError(() => t('validation.mustBeNumber')).required().min(0.01),
  method: yup.string().required(),
  paid_at: yup.string().nullable(),
  reference: yup.string().nullable().max(255),
  notes: yup.string().nullable().max(2000),
})

const hexColor = yup
  .string()
  .nullable()
  .matches(/^#[0-9A-Fa-f]{6}$/, { excludeEmptyString: true, message: () => t('validation.hexColor') })

const timeOfDay = yup
  .string()
  .nullable()
  .matches(/^([01]\d|2[0-3]):[0-5]\d$/, { excludeEmptyString: true, message: () => t('validation.timeOfDay') })

export const settingsSchema = yup.object({
  name: yup.string().required().max(255),
  email: yup.string().required().email(),
  phone: yup.string().nullable().max(50),
  address: yup.string().nullable().max(1000),
  currency: yup.string().required().length(3).uppercase(),
  timezone: yup.string().required().max(100),
  invoice_prefix: yup.string().nullable().max(20),
  default_tax_rate: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0).max(100),
  default_due_days: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(0).max(365),
  invoice_footer: yup.string().nullable().max(2000),
  primary_color: hexColor,
  secondary_color: hexColor,
  attendance_expected_start_time: timeOfDay,
  booking_reminders_enabled: yup.boolean(),
  invoice_reminders_enabled: yup.boolean(),
})

export const planSchema = yup.object({
  name: yup.string().required().max(255),
  code: yup.string().required().max(100),
  description: yup.string().nullable().max(2000),
  price_monthly: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  price_quarterly: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  price_yearly: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().min(0),
  max_users: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(1),
  max_branches: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(1),
  storage_limit_gb: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(0),
  monthly_order_limit: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(0),
  trial_days: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(0),
  has_watermark_gallery: yup.boolean(),
  has_online_gallery: yup.boolean(),
  has_reports: yup.boolean(),
  has_api_access: yup.boolean(),
  has_telegram: yup.boolean(),
  is_active: yup.boolean(),
  sort_order: yup.number().typeError(() => t('validation.mustBeNumber')).nullable().integer().min(0),
})

export const branchSchema = yup.object({
  name: yup.string().required().max(255),
  address: yup.string().nullable().max(1000),
  phone: yup.string().nullable().max(30),
  is_active: yup.boolean(),
})
