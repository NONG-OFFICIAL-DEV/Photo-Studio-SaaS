import { createI18n } from 'vue-i18n'
import en from '@/locales/en.json'
import km from '@/locales/km.json'

export default createI18n({
  legacy: false,
  locale: localStorage.getItem('photo_studio_locale') || 'en',
  fallbackLocale: 'en',
  messages: { en, km },
})
