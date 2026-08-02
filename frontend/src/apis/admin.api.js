import http from './api'

export const getAdminAnalyticsApi = (params = {}) => http.get('/v1/admin/analytics', { params })

export const getAdminTenantsApi = params => http.get('/v1/admin/tenants', { params })

export const getAdminTenantApi = id => http.get(`/v1/admin/tenants/${id}`)

export const suspendAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/suspend`)

export const activateAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/activate`)

export const changeAdminSubscriptionPlanApi = (tenantId, planId) =>
  http.put(`/v1/admin/tenants/${tenantId}/subscription/plan`, { plan_id: planId })

export const renewAdminSubscriptionApi = (tenantId, billingCycle) =>
  http.post(`/v1/admin/tenants/${tenantId}/subscription/renew`, { billing_cycle: billingCycle })

export const cancelAdminSubscriptionApi = tenantId => http.post(`/v1/admin/tenants/${tenantId}/subscription/cancel`)

export const resumeAdminSubscriptionApi = tenantId => http.post(`/v1/admin/tenants/${tenantId}/subscription/resume`)

export const suspendAdminSubscriptionApi = tenantId => http.post(`/v1/admin/tenants/${tenantId}/subscription/suspend`)

export const reactivateAdminSubscriptionApi = tenantId => http.post(`/v1/admin/tenants/${tenantId}/subscription/reactivate`)

export const getAdminSubscriptionPaymentsApi = tenantId => http.get(`/v1/admin/tenants/${tenantId}/subscription/payments`)

export const getAdminPlansApi = params => http.get('/v1/admin/plans', { params })

export const createAdminPlanApi = payload => http.post('/v1/admin/plans', payload)

export const updateAdminPlanApi = (id, payload) => http.put(`/v1/admin/plans/${id}`, payload)

export const deleteAdminPlanApi = id => http.delete(`/v1/admin/plans/${id}`)

export const getAdminRolePermissionsApi = () => http.get('/v1/admin/role-permissions')

export const updateAdminRolePermissionsApi = (role, permissions) =>
  http.put(`/v1/admin/role-permissions/${role}`, { permissions })
