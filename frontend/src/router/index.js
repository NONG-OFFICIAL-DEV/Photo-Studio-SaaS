import { createRouter, createWebHistory } from 'vue-router'
import { routes } from '@/router/routes'
import { useAuthStore } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.initialize()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.isSuperAdmin ? 'admin-analytics' : 'dashboard' }
  }

  /*
   * Super admins have no tenant_id and no tenant RBAC — they operate only
   * inside the /admin panel. Regular tenant routes are off-limits to them
   * and vice versa, each redirecting into the other's home.
   */
  if (to.meta.requiresSuperAdmin && !auth.isSuperAdmin) {
    return { name: 'dashboard' }
  }

  if (to.meta.requiresAuth && !to.meta.requiresSuperAdmin && auth.isSuperAdmin) {
    return { name: 'admin-analytics' }
  }

  if (to.meta.permission && !auth.hasPermission(to.meta.permission)) {
    return { name: 'dashboard' }
  }

  if (to.meta.planFeature && !auth.hasFeature(to.meta.planFeature)) {
    return { name: 'dashboard' }
  }

  return true
})
