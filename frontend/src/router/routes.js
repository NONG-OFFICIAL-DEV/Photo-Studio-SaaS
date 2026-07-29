const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const DefaultLayout = () => import('@/layouts/DefaultLayout.vue')

/*
 * IMPORTANT: this array has three top-level route groups that all share
 * the literal path '/' (one per layout: guest-only auth pages, non-guarded
 * auth pages, and the authenticated app shell). Vue Router matches path
 * strings in registration order and will resolve bare '/' against the
 * FIRST group whose path matches — even if that group has no child route
 * that actually renders anything there. The authenticated group (below)
 * is the only one with a real child at '' (dashboard), so it MUST be
 * registered first, or visiting '/' resolves to a layout with an empty
 * <router-view> (you'd see the layout chrome but no page content) instead
 * of correctly landing on/redirecting from the dashboard route.
 */
export const routes = [
  {
    path: '/',
    component: DefaultLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/pages/Dashboard.vue'), meta: { titleKey: 'menu.dashboard' } },
      {
        path: 'customers',
        name: 'customers',
        component: () => import('@/pages/customers/CustomersList.vue'),
        meta: { titleKey: 'menu.customers', permission: 'customers.view' },
      },
      {
        path: 'bookings',
        name: 'bookings',
        component: () => import('@/pages/bookings/BookingsList.vue'),
        meta: { titleKey: 'menu.bookings', permission: 'bookings.view' },
      },
      {
        path: 'bookings/calendar',
        name: 'bookings-calendar',
        component: () => import('@/pages/bookings/BookingsCalendarPage.vue'),
        meta: { titleKey: 'menu.bookings', permission: 'bookings.view' },
      },
      {
        path: 'services',
        name: 'services',
        component: () => import('@/pages/services/ServicesList.vue'),
        meta: { titleKey: 'menu.services', permission: 'services.view' },
      },
      {
        path: 'packages',
        name: 'packages',
        component: () => import('@/pages/packages/PackagesList.vue'),
        meta: { titleKey: 'menu.packages', permission: 'packages.view' },
      },
      {
        path: 'orders',
        name: 'orders',
        component: () => import('@/pages/orders/OrdersList.vue'),
        meta: { titleKey: 'menu.orders', permission: 'orders.view' },
      },
      {
        path: 'editing-queue',
        name: 'editing-queue',
        component: () => import('@/pages/editing/EditingQueueList.vue'),
        meta: { titleKey: 'menu.editingQueue', permission: 'editing.view' },
      },
      {
        path: 'albums',
        name: 'albums',
        component: () => import('@/pages/albums/AlbumsList.vue'),
        meta: { titleKey: 'menu.albums', permission: 'albums.view' },
      },
      {
        path: 'invoices',
        name: 'invoices',
        component: () => import('@/pages/invoices/InvoicesList.vue'),
        meta: { titleKey: 'menu.invoices', permission: 'invoices.view' },
      },
      {
        path: 'expenses',
        name: 'expenses',
        component: () => import('@/pages/expenses/ExpensesList.vue'),
        meta: { titleKey: 'menu.expenses', permission: 'expenses.view' },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: () => import('@/pages/inventory/InventoryList.vue'),
        meta: { titleKey: 'menu.inventory', permission: 'inventory.view' },
      },
      {
        path: 'employees',
        name: 'employees',
        component: () => import('@/pages/employees/EmployeesPage.vue'),
        meta: { titleKey: 'menu.employees', permission: 'users.view' },
      },
      {
        path: 'reports',
        name: 'reports',
        component: () => import('@/pages/reports/ReportsPage.vue'),
        meta: { titleKey: 'menu.reports', permission: 'reports.view' },
      },
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    meta: { guestOnly: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/auth/Login.vue') },
      { path: 'register', name: 'register', component: () => import('@/pages/auth/Register.vue') },
      { path: 'forgot-password', name: 'forgot-password', component: () => import('@/pages/auth/ForgotPassword.vue') },
    ],
  },
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: 'reset-password', name: 'reset-password', component: () => import('@/pages/auth/ResetPassword.vue') },
      { path: 'email-verified', name: 'email-verified', component: () => import('@/pages/auth/EmailVerified.vue') },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/pages/NotFound.vue'),
  },
]
