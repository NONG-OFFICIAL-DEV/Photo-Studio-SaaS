<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const appStore = useAppStore()

const logoutLoading = ref(false)

const menuItems = [
  { title: t('admin.menu.analytics'), icon: 'mdi-view-dashboard-outline', to: { name: 'admin-analytics' } },
  { title: t('admin.menu.tenants'), icon: 'mdi-domain', to: { name: 'admin-tenants' } },
  { title: t('admin.menu.plans'), icon: 'mdi-shape-outline', to: { name: 'admin-plans' } },
]

async function handleLogout() {
  logoutLoading.value = true
  try {
    await auth.logout()
    appStore.notify(t('auth.logoutSuccess'))
    router.push({ name: 'login' })
  } finally {
    logoutLoading.value = false
  }
}

function toggleLocale() {
  appStore.setLocale(appStore.locale === 'en' ? 'km' : 'en')
}
</script>

<template>
  <v-navigation-drawer v-model="appStore.drawer" width="260">
    <div class="d-flex align-center pa-4 ga-2">
      <v-icon icon="mdi-shield-crown-outline" size="28" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">{{ t('admin.panelName') }}</span>
    </div>

    <v-divider />

    <v-list nav density="comfortable">
      <v-list-item
        v-for="item in menuItems"
        :key="item.title"
        :to="item.to"
        exact
        :prepend-icon="item.icon"
        :title="item.title"
        rounded="lg"
      />
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border>
    <v-app-bar-nav-icon @click="appStore.drawer = !appStore.drawer" />

    <v-spacer />

    <v-chip variant="tonal" color="warning" class="mr-2" prepend-icon="mdi-shield-crown-outline">
      {{ t('admin.panelName') }}
    </v-chip>

    <v-btn icon variant="text" @click="toggleLocale">
      <v-icon icon="mdi-translate" />
    </v-btn>

    <v-btn icon variant="text" @click="appStore.toggleTheme">
      <v-icon :icon="appStore.theme === 'light' ? 'mdi-weather-night' : 'mdi-white-balance-sunny'" />
    </v-btn>

    <v-menu>
      <template #activator="{ props: menuProps }">
        <v-btn icon v-bind="menuProps">
          <v-avatar color="primary" size="36">
            <span class="text-body-2">{{ auth.user?.name?.charAt(0)?.toUpperCase() }}</span>
          </v-avatar>
        </v-btn>
      </template>
      <v-list density="comfortable" min-width="200">
        <v-list-item :title="auth.user?.name" :subtitle="auth.user?.email" />
        <v-divider />
        <v-list-item
          :title="t('menu.logout')"
          prepend-icon="mdi-logout"
          :disabled="logoutLoading"
          @click="handleLogout"
        />
      </v-list>
    </v-menu>
  </v-app-bar>

  <v-main>
    <v-container fluid>
      <router-view />
    </v-container>
  </v-main>
</template>
