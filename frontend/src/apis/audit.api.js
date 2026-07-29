import http from './api'

export const getActivityLogApi = params => http.get('/v1/audit/activity', { params })

export const getAuditLogApi = params => http.get('/v1/audit/log', { params })

export const getLoginHistoryApi = params => http.get('/v1/audit/login-history', { params })

export const getSecurityEventsApi = params => http.get('/v1/audit/security-events', { params })

export const getApiLogsApi = params => http.get('/v1/audit/api-logs', { params })

export const getAdminActivityLogApi = params => http.get('/v1/admin/audit/activity', { params })

export const getAdminAuditLogApi = params => http.get('/v1/admin/audit/log', { params })

export const getAdminLoginHistoryApi = params => http.get('/v1/admin/audit/login-history', { params })

export const getAdminSecurityEventsApi = params => http.get('/v1/admin/audit/security-events', { params })

export const getAdminApiLogsApi = params => http.get('/v1/admin/audit/api-logs', { params })
