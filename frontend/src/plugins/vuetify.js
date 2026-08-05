import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { useI18n } from 'vue-i18n'
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n'
import i18n from './i18n'
import { KhmerDateAdapter } from './khmerDateAdapter'

/*
 * Material Design 3-inspired palette. Swap these tokens for the studio's
 * brand colors later via Settings > Theme (Phase: Settings module) — the
 * component layer never hardcodes color, only these theme tokens.
 */
const lightTheme = {
  dark: false,
  colors: {
    primary: '#6750A4',
    'primary-darken-1': '#4F378B',
    secondary: '#625B71',
    tertiary: '#7D5260',
    error: '#B3261E',
    success: '#2E7D32',
    warning: '#F9A825',
    info: '#0288D1',
    background: '#FFFBFE',
    surface: '#FFFBFE'
  },
}

const darkTheme = {
  dark: true,
  colors: {
    primary: '#D0BCFF',
    'primary-darken-1': '#B69DF8',
    secondary: '#CCC2DC',
    tertiary: '#EFB8C8',
    error: '#F2B8B5',
    success: '#81C784',
    warning: '#FFD54F',
    info: '#4FC3F7',
    background: '#1C1B1F',
    surface: '#1C1B1F'
  },
}

export default createVuetify({
  components,
  directives,
  locale: {
    adapter: createVueI18nAdapter({ i18n, useI18n }),
  },
  date: {
    adapter: KhmerDateAdapter,
  },
  theme: {
    defaultTheme: localStorage.getItem('photo_studio_theme') || 'light',
    themes: {
      light: lightTheme,
      dark: darkTheme,
    },
  },
  defaults: {
    VBtn: { rounded: 'lg' },
    VCard: { rounded: 'lg' },
    VCombobox: { variant: 'outlined', density: 'comfortable' },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable' },
  },
})
