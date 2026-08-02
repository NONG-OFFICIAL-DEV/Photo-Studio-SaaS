import http from './api'

export const getNotificationsApi = (params = {}) => http.get('/v1/notifications', { params })

export const getUnreadNotificationCountApi = () => http.get('/v1/notifications/unread-count')

export const markNotificationReadApi = id => http.post(`/v1/notifications/${id}/read`)

export const markAllNotificationsReadApi = () => http.post('/v1/notifications/read-all')
