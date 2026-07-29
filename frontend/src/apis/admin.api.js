import http from './api'

export const getAdminAnalyticsApi = () => http.get('/v1/admin/analytics')

export const getAdminTenantsApi = params => http.get('/v1/admin/tenants', { params })

export const getAdminTenantApi = id => http.get(`/v1/admin/tenants/${id}`)

export const suspendAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/suspend`)

export const activateAdminTenantApi = id => http.post(`/v1/admin/tenants/${id}/activate`)

export const getAdminPlansApi = params => http.get('/v1/admin/plans', { params })

export const createAdminPlanApi = payload => http.post('/v1/admin/plans', payload)

export const updateAdminPlanApi = (id, payload) => http.put(`/v1/admin/plans/${id}`, payload)

export const deleteAdminPlanApi = id => http.delete(`/v1/admin/plans/${id}`)
