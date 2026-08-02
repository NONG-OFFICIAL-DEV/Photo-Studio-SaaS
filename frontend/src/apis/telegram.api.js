import http from './api'

export const getTelegramActivityApi = (params = {}) => http.get('/v1/telegram/activity', { params })
