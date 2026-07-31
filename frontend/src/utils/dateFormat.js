import { format, parseISO } from 'date-fns'

/*
 * The one place every date/time gets displayed from. Always dd/MM/yyyy
 * with a numeric month — no month-name tokens (MMM/MMMM) anywhere here,
 * since those need a translated locale object per language and this app
 * would rather not maintain that. parseISO (not `new Date(string)`)
 * correctly reads a bare "yyyy-MM-dd" as a local calendar date instead of
 * UTC midnight, which would roll back a day in timezones behind UTC.
 */
function toDate(value) {
  if (!value) return null
  const date = typeof value === 'string' ? parseISO(value) : value
  return Number.isNaN(date?.getTime()) ? null : date
}

export function formatDate(value) {
  const date = toDate(value)
  return date ? format(date, 'dd/MM/yyyy') : ''
}

export function formatDateTime(value) {
  const date = toDate(value)
  return date ? format(date, 'dd/MM/yyyy HH:mm') : ''
}

export function formatTime(value) {
  const date = toDate(value)
  return date ? format(date, 'HH:mm') : ''
}
