import { ref } from 'vue'
import { defineStore } from 'pinia'

const THEME_KEY = 'photo_studio_theme'
const LOCALE_KEY = 'photo_studio_locale'

export const useAppStore = defineStore('app', () => {
  const theme = ref(localStorage.getItem(THEME_KEY) || 'light')
  const locale = ref(localStorage.getItem(LOCALE_KEY) || 'en')
  const drawer = ref(true)
  const globalLoading = ref(false)

  const snackbar = ref({ show: false, text: '', color: 'success' })

  function toggleTheme() {
    theme.value = theme.value === 'light' ? 'dark' : 'light'
    localStorage.setItem(THEME_KEY, theme.value)
  }

  function setLocale(value) {
    locale.value = value
    localStorage.setItem(LOCALE_KEY, value)
  }

  function notify(text, color = 'success') {
    snackbar.value = { show: true, text, color }
  }

  return { theme, locale, drawer, globalLoading, snackbar, toggleTheme, setLocale, notify }
})
