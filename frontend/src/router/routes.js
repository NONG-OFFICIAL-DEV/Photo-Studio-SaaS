const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const DefaultLayout = () => import('@/layouts/DefaultLayout.vue')

export const routes = [
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
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/pages/NotFound.vue'),
  },
]
