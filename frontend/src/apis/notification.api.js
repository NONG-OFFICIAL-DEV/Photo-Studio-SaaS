import http from './api'

export const getNotificationsApi = (params = {}) => http.get('/v1/notifications', { params })

export const getUnreadNotificationCountApi = () => http.get('/v1/notifications/unread-count')

export const markNotificationReadApi = id => http.post(`/v1/notifications/${id}/read`)

export const markAllNotificationsReadApi = () => http.post('/v1/notifications/read-all')

export const getNotificationPreferencesApi = () => http.get('/v1/notifications/preferences')

export const updateNotificationPreferencesApi = channels => http.put('/v1/notifications/preferences', channels)

export const linkTelegramNotificationsApi = () => http.post('/v1/notifications/telegram/link')

export const unlinkTelegramNotificationsApi = () => http.post('/v1/notifications/telegram/unlink')
