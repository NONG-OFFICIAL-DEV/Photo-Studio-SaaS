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

export const serviceSchema = yup.object({
  category_id: yup.string().nullable(),
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(2000),
  deliverables: yup.string().nullable().max(2000),
  price: yup.number().typeError('Price must be a number').required().min(0),
  pricing_unit: yup.string().required().oneOf(['fixed', 'per_hour', 'per_person', 'per_photo']),
  duration_minutes: yup.number().typeError('Must be a number').nullable().min(1),
  is_active: yup.boolean(),
})

export const serviceCategorySchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(1000),
})

export const serviceAddOnSchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(1000),
  price: yup.number().typeError('Price must be a number').required().min(0),
})

export const orderSchema = yup.object({
  customer_id: yup.string().required('Customer is required'),
  booking_id: yup.string().nullable(),
  discount_amount: yup.number().typeError('Must be a number').nullable().min(0),
  notes: yup.string().nullable().max(2000),
})

export const cancelOrderSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const packageSchema = yup.object({
  name: yup.string().required().max(255),
  description: yup.string().nullable().max(2000),
  discount_type: yup.string().nullable(),
  discount_value: yup.number().typeError('Must be a number').nullable().min(0)
    .when('discount_type', {
      is: 'percent',
      then: schema => schema.max(100, 'A percent discount cannot exceed 100'),
    }),
  override_price: yup.number().typeError('Must be a number').nullable().min(0),
  is_active: yup.boolean(),
})

export const expenseCategorySchema = yup.object({
  name: yup.string().required().max(255),
})

export const attendanceSchema = yup.object({
  user_id: yup.string().required('Employee is required'),
  date: yup.string().required('Date is required'),
  status: yup.string().required(),
  reason: yup.string().nullable().max(1000),
})

export const commissionEntrySchema = yup.object({
  user_id: yup.string().required('Employee is required'),
  order_id: yup.string().nullable(),
  amount: yup.number().typeError('Must be a number').required().min(0.01),
  earned_date: yup.string().required('Date is required'),
  notes: yup.string().nullable().max(2000),
})

export const payrollEntrySchema = yup.object({
  user_id: yup.string().required('Employee is required'),
  period_label: yup.string().required().max(255),
  period_start: yup.string().required('Start date is required'),
  period_end: yup.string().required('End date is required'),
  base_pay: yup.number().typeError('Must be a number').nullable().min(0),
  commission_total: yup.number().typeError('Must be a number').nullable().min(0),
  deductions: yup.number().typeError('Must be a number').nullable().min(0),
  notes: yup.string().nullable().max(2000),
})

export const employmentSchema = yup.object({
  pay_type: yup.string().required(),
  base_pay: yup.number().typeError('Must be a number').nullable().min(0),
  commission_rate: yup.number().typeError('Must be a number').nullable().min(0).max(100),
})

export const expenseSchema = yup.object({
  category_id: yup.string().nullable(),
  amount: yup.number().typeError('Must be a number').required().min(0.01),
  expense_date: yup.string().required('Expense date is required'),
  vendor: yup.string().nullable().max(255),
  payment_method: yup.string().required(),
  notes: yup.string().nullable().max(2000),
})

export const inventoryItemSchema = yup.object({
  name: yup.string().required().max(255),
  sku: yup.string().nullable().max(100),
  unit: yup.string().required().max(50),
  category: yup.string().nullable().max(100),
  reorder_threshold: yup.number().typeError('Must be a number').nullable().min(0),
  is_active: yup.boolean(),
})

export const inventoryMovementSchema = yup.object({
  type: yup.string().required(),
  quantity: yup.number().typeError('Must be a number').required().min(0.01),
  reason: yup.string().nullable().max(1000),
  moved_at: yup.string().nullable(),
})

export const albumSchema = yup.object({
  name: yup.string().required().max(255),
  customer_id: yup.string().nullable(),
  order_id: yup.string().nullable(),
  description: yup.string().nullable().max(2000),
  expected_photo_count: yup.number().typeError('Must be a number').nullable().min(0),
})

export const invoiceSchema = yup.object({
  customer_id: yup.string().nullable().when('order_id', {
    is: val => !val,
    then: schema => schema.required('Customer is required unless created from an order'),
  }),
  order_id: yup.string().nullable(),
  issue_date: yup.string().nullable(),
  due_date: yup.string().nullable(),
  discount_amount: yup.number().typeError('Must be a number').nullable().min(0),
  tax_rate: yup.number().typeError('Must be a number').nullable().min(0).max(100),
  notes: yup.string().nullable().max(2000),
})

export const voidInvoiceSchema = yup.object({
  reason: yup.string().required().max(1000),
})

export const paymentSchema = yup.object({
  amount: yup.number().typeError('Must be a number').required().min(0.01),
  method: yup.string().required(),
  paid_at: yup.string().nullable(),
  reference: yup.string().nullable().max(255),
  notes: yup.string().nullable().max(2000),
})

const hexColor = yup
  .string()
  .nullable()
  .matches(/^#[0-9A-Fa-f]{6}$/, { excludeEmptyString: true, message: 'Must be a hex color like #6750A4' })

export const settingsSchema = yup.object({
  name: yup.string().required().max(255),
  email: yup.string().required().email(),
  phone: yup.string().nullable().max(50),
  address: yup.string().nullable().max(1000),
  currency: yup.string().required().length(3).uppercase(),
  timezone: yup.string().required().max(100),
  invoice_prefix: yup.string().nullable().max(20),
  default_tax_rate: yup.number().typeError('Must be a number').nullable().min(0).max(100),
  default_due_days: yup.number().typeError('Must be a number').nullable().integer().min(0).max(365),
  invoice_footer: yup.string().nullable().max(2000),
  primary_color: hexColor,
  secondary_color: hexColor,
})
