import { VuetifyDateAdapter } from 'vuetify/date/adapters/vuetify'

/*
 * Two things this app wants that Vuetify's default date adapter doesn't
 * give it:
 *
 * 1. Numeric month everywhere (dd/MM/yyyy), for every locale — month
 *    NAMES need a translated locale object per language and are the kind
 *    of thing this app would rather not maintain (see src/utils/
 *    dateFormat.js, which applies the same rule to plain record dates).
 *    This affects the calendar popup's own month/year navigation and any
 *    other date the Vuetify components format internally.
 *
 * 2. Khmer weekday letters — 'km' isn't in Vuetify's own locale-code map
 *    (falls back to the raw 'km' string) and, in practice, still renders
 *    English weekday names in this app's target browsers even though
 *    Intl.DateTimeFormat('km', ...) works fine standalone. Rendered here
 *    directly instead of fighting Intl's locale resolution.
 */
const KHMER_WEEKDAYS = ['អា', 'ច', 'អ', 'ព', 'ព្រ', 'សុ', 'ស']

function pad(n) {
  return String(n).padStart(2, '0')
}

export class KhmerDateAdapter extends VuetifyDateAdapter {
  format(date, formatString) {
    const d = this.date(date) ?? new Date()
    const day = pad(d.getDate())
    const month = pad(d.getMonth() + 1)
    const year = d.getFullYear()
    const weekday = this.locale === 'km' ? KHMER_WEEKDAYS[d.getDay()] : super.format(date, 'weekdayShort')

    switch (formatString) {
      case 'month':
      case 'monthShort':
        return month
      case 'monthAndYear':
        return `${month}/${year}`
      case 'monthAndDate':
        return `${day}/${month}`
      case 'normalDate':
      case 'shortDate':
        return `${day}/${month}`
      case 'fullDate':
        return `${day}/${month}/${year}`
      case 'fullDateWithWeekday':
        return `${weekday}, ${day}/${month}/${year}`
      case 'normalDateWithWeekday':
        return `${weekday}, ${day}/${month}`
      case 'weekday':
      case 'weekdayShort':
        return weekday
      default:
        return super.format(date, formatString)
    }
  }

  getWeekdays(firstDayOfWeek) {
    if (this.locale !== 'km') return super.getWeekdays(firstDayOfWeek)

    const start = firstDayOfWeek !== undefined ? Number(firstDayOfWeek) : 0
    return Array.from({ length: 7 }, (_, i) => KHMER_WEEKDAYS[(start + i) % 7])
  }
}
