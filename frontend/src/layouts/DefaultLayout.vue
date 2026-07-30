<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const appStore = useAppStore()

const logoutLoading = ref(false)

/* Dashboard is a standalone main-menu link — every other module lives in a
 * collapsible main-menu group (dropdown) with its pages as submenu items. */
const dashboardItem = computed(() => ({
  title: t('menu.dashboard'),
  icon: 'mdi-view-dashboard-outline',
  to: { name: 'dashboard' },
  permission: 'dashboard.view',
}))

/*
 * Menu permission: each submenu entry declares the permission required to
 * see it. As modules ship (bookings, gallery, invoices, ...) they add an
 * entry here — the item only renders if the current user's permission set
 * includes it, and a whole group disappears once all its items do.
 */
const tenantMenuGroups = computed(() => [
  {
    value: 'sales',
    header: t('menu.groups.sales'),
    icon: 'mdi-handshake-outline',
    items: [
      {
        title: t('menu.customers'),
        icon: 'mdi-account-group-outline',
        to: { name: 'customers' },
        permission: 'customers.view',
      },
      {
        title: t('menu.bookings'),
        icon: 'mdi-calendar-check-outline',
        to: { name: 'bookings' },
        permission: 'bookings.view',
      },
    ],
  },
  {
    value: 'catalog',
    header: t('menu.groups.catalog'),
    icon: 'mdi-shape-outline',
    items: [
      { title: t('menu.services'), icon: 'mdi-tag-outline', to: { name: 'services' }, permission: 'services.view' },
      {
        title: t('menu.packages'),
        icon: 'mdi-package-variant-closed',
        to: { name: 'packages' },
        permission: 'packages.view',
      },
    ],
  },
  {
    value: 'production',
    header: t('menu.groups.production'),
    icon: 'mdi-image-multiple-outline',
    items: [
      { title: t('menu.orders'), icon: 'mdi-cart-outline', to: { name: 'orders' }, permission: 'orders.view' },
      {
        title: t('menu.editingQueue'),
        icon: 'mdi-image-edit-outline',
        to: { name: 'editing-queue' },
        permission: 'editing.view',
      },
      {
        title: t('menu.albums'),
        icon: 'mdi-book-open-page-variant-outline',
        to: { name: 'albums' },
        permission: 'albums.view',
      },
    ],
  },
  {
    value: 'finance',
    header: t('menu.groups.finance'),
    icon: 'mdi-cash-multiple',
    items: [
      {
        title: t('menu.invoices'),
        icon: 'mdi-receipt-text-outline',
        to: { name: 'invoices' },
        permission: 'invoices.view',
      },
      { title: t('menu.expenses'), icon: 'mdi-cash-minus', to: { name: 'expenses' }, permission: 'expenses.view' },
      {
        title: t('menu.inventory'),
        icon: 'mdi-archive-outline',
        to: { name: 'inventory' },
        permission: 'inventory.view',
      },
    ],
  },
  {
    value: 'team',
    header: t('menu.groups.team'),
    icon: 'mdi-account-multiple-outline',
    items: [
      {
        title: t('menu.employees'),
        icon: 'mdi-badge-account-horizontal-outline',
        to: { name: 'employees' },
        permission: 'users.view',
      },
      { title: t('menu.reports'), icon: 'mdi-chart-box-outline', to: { name: 'reports' }, permission: 'reports.view' },
    ],
  },
  {
    value: 'system',
    header: t('menu.groups.system'),
    icon: 'mdi-tune-vertical',
    items: [
      {
        title: t('menu.settings'),
        icon: 'mdi-cog-outline',
        to: { name: 'settings' },
        permission: 'tenant.settings.manage',
      },
      { title: t('menu.audit'), icon: 'mdi-shield-search', to: { name: 'audit' }, permission: 'audit.view' },
      { title: t('menu.billing'), icon: 'mdi-credit-card-outline', to: { name: 'billing' }, permission: 'tenant.billing.manage' },
    ],
  },
])

const visibleTenantGroups = computed(() =>
  tenantMenuGroups.value
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.permission || auth.hasPermission(item.permission)),
    }))
    .filter((group) => group.items.length > 0),
)

/*
 * Super admins have no tenant_id and no tenant RBAC — they operate only
 * inside /admin/* routes, so they get a completely different (unfiltered)
 * menu instead of the permission-filtered tenant menu above. Only 4 items,
 * so it stays a single flat main menu rather than being grouped too.
 */
const adminMenuItems = [
  { title: t('admin.menu.analytics'), icon: 'mdi-view-dashboard-outline', to: { name: 'admin-analytics' } },
  { title: t('admin.menu.tenants'), icon: 'mdi-domain', to: { name: 'admin-tenants' } },
  { title: t('admin.menu.plans'), icon: 'mdi-shape-outline', to: { name: 'admin-plans' } },
  { title: t('admin.menu.audit'), icon: 'mdi-shield-search', to: { name: 'admin-audit' } },
]

/*
 * Custom collapsible groups instead of Vuetify's <v-list-group> — that
 * component nests each child through its own indentation layer *on top of*
 * the icon gutter, which stacks into a much wider left margin than a
 * modern sidebar wants. Rolling this by hand (a toggle + v-expand-transition)
 * gives full control over the submenu's indent (see .nav-submenu below).
 */
const openGroups = ref([])

function isGroupOpen(value) {
  return openGroups.value.includes(value)
}

function toggleGroup(value) {
  openGroups.value = isGroupOpen(value) ? openGroups.value.filter((v) => v !== value) : [...openGroups.value, value]
}

function isGroupActive(group) {
  return group.items.some((item) => item.to.name === route.name)
}

// Keeps the submenu open when the current page belongs to it, so a direct
// link (or a page refresh) still shows the user which section they're in.
watch(
  () => route.name,
  () => {
    const activeGroup = visibleTenantGroups.value.find((group) => isGroupActive(group))

    if (activeGroup && !isGroupOpen(activeGroup.value)) {
      openGroups.value = [...openGroups.value, activeGroup.value]
    }
  },
  { immediate: true },
)

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
  <v-app-bar flat border height="64">
    <template #prepend>
      <v-app-bar-nav-icon @click="appStore.drawer = !appStore.drawer" />
      <v-divider vertical inset class="mx-1" />
    </template>

    <v-app-bar-title>
      <div class="d-flex align-center ga-2">
        <v-icon :icon="auth.isSuperAdmin ? 'mdi-shield-crown-outline' : 'mdi-camera-iris'" size="24" color="primary" />
        <span class="text-subtitle-1 font-weight-bold">{{
          auth.isSuperAdmin ? t('admin.panelName') : t('app.name')
        }}</span>
      </div>
    </v-app-bar-title>

    <v-spacer />

    <div class="d-flex align-center ga-2">
      <v-chip
        v-if="auth.isSuperAdmin"
        variant="tonal"
        color="warning"
        size="small"
        prepend-icon="mdi-shield-crown-outline"
        rounded="lg"
      >
        {{ t('admin.panelName') }}
      </v-chip>
      <v-chip v-else-if="auth.tenant" variant="tonal" color="primary" size="small" prepend-icon="mdi-domain" rounded="lg">
        {{ auth.tenant.name }}
      </v-chip>

      <v-divider vertical inset class="mx-1" />
      <v-menu
        location="bottom end"
        transition="scale-transition"
        :close-on-content-click="false"
        min-width="280"
        max-width="320"
      >
        <!-- Activator Button -->
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" variant="text" rounded="lg" class="profile-btn px-2 text-none me-4">
            <v-avatar color="primary" size="36" class="elevation-0">
              <span class="text-subtitle-2 font-weight-bold text-white">
                {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
              </span>
            </v-avatar>
            <span class="d-none d-sm-inline-block text-body-2 font-weight-medium ml-2 mr-1">
              {{ auth.user?.name }}
            </span>
            <v-icon icon="mdi-chevron-down" size="18" class="text-medium-emphasis ml-1" />
          </v-btn>
        </template>
        <!-- Profile Card Dropdown -->
        <v-card elevation="0" rounded="lg" class="overflow-hidden border">
          <!-- User Profile Info Header -->
          <div class="pa-4 bg-opacity-20 border-b">
            <div class="d-flex align-center ga-3">
              <v-avatar color="primary" size="44" class="elevation-2">
                <span class="text-h6 font-weight-bold text-white">
                  {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
                </span>
              </v-avatar>
              <div class="overflow-hidden">
                <div class="text-subtitle-2 font-weight-bold text-truncate">
                  {{ auth.user?.name }}
                </div>
                <div class="text-caption text-medium-emphasis text-truncate mb-1" :title="auth.user?.email">
                  {{ auth.user?.email }}
                </div>
                <v-chip
                  size="x-small"
                  :color="auth.isSuperAdmin ? 'warning' : 'primary'"
                  variant="flat"
                  class="font-weight-medium"
                >
                  {{ auth.isSuperAdmin ? 'Super Admin' : auth.tenant?.name || 'User' }}
                </v-chip>
              </div>
            </div>
          </div>

          <!-- Quick Actions & Links -->
          <v-list density="comfortable" class="py-1">
            <v-list-item
              prepend-icon="mdi-cash-multiple"
              :title="t('menu.billing')"
              rounded="md"
              class="mx-1 my-1"
              to="/billing"
            />

            <v-divider class="my-1" />

            <!-- Language Selector Toggle -->
            <v-list-item rounded="md" class="mx-1 my-1" @click="toggleLocale">
              <template #prepend>
                <v-icon icon="mdi-translate" size="20" class="mr-2" />
              </template>
              <v-list-item-title class="text-body-2">{{ t('common.toggleLanguage') }}</v-list-item-title>
              <template #append>
                <v-chip size="x-small" variant="tonal" class="font-weight-bold">
                  {{ appStore.locale.toUpperCase() }}
                </v-chip>
              </template>
            </v-list-item>

            <!-- Theme Toggle -->
            <v-list-item rounded="md" class="mx-1 my-1" @click="appStore.toggleTheme">
              <template #prepend>
                <v-icon
                  :icon="appStore.theme === 'light' ? 'mdi-weather-night' : 'mdi-white-balance-sunny'"
                  size="20"
                  class="mr-2"
                />
              </template>
              <v-list-item-title class="text-body-2">{{ t('common.toggleTheme') }}</v-list-item-title>
              <template #append>
                <v-switch
                  :model-value="appStore.theme === 'dark'"
                  density="compact"
                  hide-details
                  color="primary"
                  class="flex-grow-0"
                  @click.stop="appStore.toggleTheme"
                />
              </template>
            </v-list-item>

            <v-divider class="my-1" />

            <!-- Logout -->
            <v-list-item
              color="error"
              rounded="md"
              class="mx-1 my-1 text-error"
              :disabled="logoutLoading"
              @click="handleLogout"
            >
              <template #prepend>
                <v-icon icon="mdi-logout" size="20" color="error" class="mr-2" />
              </template>
              <v-list-item-title class="text-body-2 font-weight-medium">
                {{ t('menu.logout') }}
              </v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card>
      </v-menu>
    </div>
  </v-app-bar>
  <v-navigation-drawer v-model="appStore.drawer" width="264">
    <!-- <v-divider /> -->

    <!--
      exact: dashboard's route has an empty child path (''), so its
      matched record shares the parent segment with every other item
      here — without exact, Vuetify's default (non-exact) active check
      marks Dashboard as active on every page, not just its own.
    -->
    <v-list v-if="auth.isSuperAdmin" nav density="comfortable" class="nav-list">
      <v-list-item
        v-for="item in adminMenuItems"
        :key="item.title"
        :to="item.to"
        exact
        :prepend-icon="item.icon"
        :title="item.title"
        rounded="lg"
      />
    </v-list>

    <v-list v-else nav density="comfortable" class="nav-list">
      <v-list-item
        :to="dashboardItem.to"
        exact
        :prepend-icon="dashboardItem.icon"
        :title="dashboardItem.title"
        rounded="lg"
      />

      <div v-for="group in visibleTenantGroups" :key="group.value">
        <v-list-item
          :active="isGroupActive(group)"
          :prepend-icon="group.icon"
          :title="group.header"
          rounded="lg"
          @click="toggleGroup(group.value)"
        >
          <template #append>
            <v-icon
              size="18"
              icon="mdi-chevron-down"
              class="nav-chevron"
              :class="{ 'nav-chevron--open': isGroupOpen(group.value) }"
            />
          </template>
        </v-list-item>

        <v-expand-transition>
          <div v-show="isGroupOpen(group.value)" class="nav-submenu">
            <v-list-item
              v-for="item in group.items"
              :key="item.title"
              :to="item.to"
              exact
              :prepend-icon="item.icon"
              :title="item.title"
              rounded="lg"
              density="compact"
              class="nav-subitem"
            />
          </div>
        </v-expand-transition>
      </div>
    </v-list>
  </v-navigation-drawer>

  <v-main>
    <v-container fluid>
      <router-view />
    </v-container>
  </v-main>
</template>

<style scoped>
.nav-list {
  padding-inline: 8px;
}

.nav-chevron {
  transition: transform 0.2s ease;
  opacity: 0.6;
}

.nav-chevron--open {
  transform: rotate(180deg);
}

/* Modern, compact submenu: a thin guide line instead of Vuetify's default
 * per-level indent, so sub-items sit close to their parent rather than
 * pushed far right. */
.nav-submenu {
  margin: 2px 0 6px 20px;
  padding-inline-start: 12px;
  border-left: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.nav-subitem {
  min-height: 36px;
  font-size: 0.8125rem;
}

.nav-subitem :deep(.v-list-item__prepend > .v-icon) {
  font-size: 18px;
  opacity: 0.8;
}
</style>
