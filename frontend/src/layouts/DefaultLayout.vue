<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const appStore = useAppStore()

const logoutLoading = ref(false)

/*
 * Menu permission: each entry declares the permission required to see it.
 * As modules ship (bookings, gallery, invoices, ...) they add an entry here
 * — the item only renders if the current user's permission set includes it.
 */
const menuItems = computed(() => [
  { title: t('menu.dashboard'), icon: 'mdi-view-dashboard-outline', to: { name: 'dashboard' }, permission: 'dashboard.view' },
  { title: t('menu.customers'), icon: 'mdi-account-group-outline', to: { name: 'customers' }, permission: 'customers.view' },
  { title: t('menu.bookings'), icon: 'mdi-calendar-check-outline', to: { name: 'bookings' }, permission: 'bookings.view' },
  { title: t('menu.services'), icon: 'mdi-tag-outline', to: { name: 'services' }, permission: 'services.view' },
  { title: t('menu.packages'), icon: 'mdi-package-variant-closed', to: { name: 'packages' }, permission: 'packages.view' },
  { title: t('menu.orders'), icon: 'mdi-cart-outline', to: { name: 'orders' }, permission: 'orders.view' },
  { title: t('menu.editingQueue'), icon: 'mdi-image-edit-outline', to: { name: 'editing-queue' }, permission: 'editing.view' },
  { title: t('menu.albums'), icon: 'mdi-book-open-page-variant-outline', to: { name: 'albums' }, permission: 'albums.view' },
  { title: t('menu.invoices'), icon: 'mdi-receipt-text-outline', to: { name: 'invoices' }, permission: 'invoices.view' },
].filter((item) => !item.permission || auth.hasPermission(item.permission)))

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
      <v-icon icon="mdi-camera-iris" size="28" color="primary" />
      <span class="text-subtitle-1 font-weight-bold">{{ t('app.name') }}</span>
    </div>

    <v-divider />

    <v-list nav density="comfortable">
      <!--
        exact: dashboard's route has an empty child path (''), so its
        matched record shares the parent segment with every other item
        here — without exact, Vuetify's default (non-exact) active check
        marks Dashboard as active on every page, not just its own.
      -->
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

    <v-chip v-if="auth.tenant" variant="tonal" color="primary" class="mr-2" prepend-icon="mdi-domain">
      {{ auth.tenant.name }}
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
