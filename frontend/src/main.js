import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from '@/App.vue'
import { router } from '@/router'
import vuetify from '@/plugins/vuetify'
import i18n from '@/plugins/i18n'
import { useAuthStore } from '@/stores/auth'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(i18n)

// A refresh-token failure anywhere in the app (see apis/api.js) ends
// the session and bounces the user back to Login, wherever they were.
window.addEventListener('auth:session-expired', () => {
  useAuthStore().clearSession()
  router.push({ name: 'login' })
})

app.mount('#app')
