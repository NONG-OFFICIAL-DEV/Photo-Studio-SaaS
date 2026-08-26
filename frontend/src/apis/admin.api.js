import http from './api'

export const getAdminAnalyticsApi = (params = {}) => http.get('/v1/admin/analytics', { params })

export const getAdminTenantsApi = params => http.get('/v1/admin/tenants', { params })

export const getAdminTenantApi = id => http.get(`/v1/admin/tenants/${id}`)

export const suspendAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/suspend`)

export const activateAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/activate`)

export const deleteAdminTenantApi = (id, confirmName) =>
  http.post(`/v1/admin/tenants/${id}/delete`, { confirm_name: confirmName })

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

export const getAdminTenantRolePermissionsApi = tenantId => http.get(`/v1/admin/tenants/${tenantId}/role-permissions`)

export const updateAdminTenantRolePermissionsApi = (tenantId, role, permissions) =>
  http.put(`/v1/admin/tenants/${tenantId}/role-permissions/${role}`, { permissions })

export const getAdminTenantUsersApi = tenantId => http.get(`/v1/admin/tenants/${tenantId}/users`)

export const deactivateAdminTenantUserApi = (tenantId, userId) =>
  http.post(`/v1/admin/tenants/${tenantId}/users/${userId}/deactivate`)

export const reactivateAdminTenantUserApi = (tenantId, userId) =>
  http.post(`/v1/admin/tenants/${tenantId}/users/${userId}/reactivate`)

export const sendAdminUserPasswordResetApi = (tenantId, userId) =>
  http.post(`/v1/admin/tenants/${tenantId}/users/${userId}/reset-password`)

export const getAdminPlatformSettingsApi = () => http.get('/v1/admin/platform-settings')

export const updateAdminPlatformSettingsApi = payload => http.put('/v1/admin/platform-settings', payload)

export const uploadAdminKhqrImageApi = (file) => {
  const formData = new FormData()
  formData.append('khqr_image', file)
  return http.post('/v1/admin/platform-settings/khqr', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}

export const getAdminPaymentClaimsApi = (params = {}) => http.get('/v1/admin/payment-claims', { params })

export const confirmAdminPaymentClaimApi = (claimId, billingCycle) =>
  http.post(`/v1/admin/payment-claims/${claimId}/confirm`, { billing_cycle: billingCycle })

export const rejectAdminPaymentClaimApi = (claimId, note) =>
  http.post(`/v1/admin/payment-claims/${claimId}/reject`, { note })
