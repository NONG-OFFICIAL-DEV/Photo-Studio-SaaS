import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@/assets/main.css'
import App from '@/App.vue'
import { router } from '@/router'
import vuetify from '@/plugins/vuetify'
import i18n from '@/plugins/i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)
app.use(i18n)

// A refresh-token failure anywhere in the app (see apis/api.js) ends
// the session and bounces the user back to Login, wherever they were.
window.addEventListener('auth:session-expired', () => {
  useAuthStore().clearSession()
  useAppStore().setSubscriptionBlocked(null)
  router.push({ name: 'login' })
})

// A blocked-subscription response anywhere in the app (see apis/api.js)
// surfaces a persistent banner instead of a toast that scrolls by
// unnoticed — cleared automatically the moment any request succeeds again
// (e.g. after the user renews via the billing page, which stays reachable
// even while blocked).
window.addEventListener('subscription:blocked', (event) => {
  useAppStore().setSubscriptionBlocked(event.detail)
})

app.mount('#app')
