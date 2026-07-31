import { VuetifyDateAdapter } from 'vuetify/date/adapters/vuetify'

/*
 * Vuetify's default date adapter formats month/weekday names via
 * Intl.DateTimeFormat(locale, ...) — 'km' isn't in Vuetify's own
 * locale-code map (falls back to the raw 'km' string) and, in practice,
 * still renders English month names in this app's target browsers. Rather
 * than fight Intl's locale resolution, this adapter renders Khmer names
 * itself and defers to the default adapter for everything else (numbers,
 * times, and every other locale).
 */
const KHMER_MONTHS = [
  'មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា',
  'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ',
]

const KHMER_WEEKDAYS = ['អា', 'ច', 'អ', 'ព', 'ព្រ', 'សុ', 'ស']

export class KhmerDateAdapter extends VuetifyDateAdapter {
  format(date, formatString) {
    if (this.locale !== 'km') return super.format(date, formatString)

    const d = this.date(date) ?? new Date()
    const day = d.getDate()
    const month = KHMER_MONTHS[d.getMonth()]
    const year = d.getFullYear()
    const weekday = KHMER_WEEKDAYS[d.getDay()]

    switch (formatString) {
      case 'month':
      case 'monthShort':
        return month
      case 'monthAndYear':
        return `${month} ${year}`
      case 'monthAndDate':
        return `${month} ${day}`
      case 'normalDate':
        return `${day} ${month}`
      case 'shortDate':
        return `${month} ${day}`
      case 'fullDate':
        return `${month} ${day}, ${year}`
      case 'fullDateWithWeekday':
        return `${weekday}, ${month} ${day}, ${year}`
      case 'normalDateWithWeekday':
        return `${weekday}, ${day} ${month}`
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
