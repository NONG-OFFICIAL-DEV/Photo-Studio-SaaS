import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { enUS, km } from 'date-fns/locale'

const DATE_FNS_LOCALES = { en: enUS, km }

/**
 * date-fns has no idea about the app's vue-i18n locale — format() always
 * renders month/weekday names in English unless you pass its own locale
 * object explicitly. This maps the app's current locale to that object.
 */
export function useDateFnsLocale() {
  const { locale } = useI18n()
  return computed(() => DATE_FNS_LOCALES[locale.value] ?? enUS)
}
