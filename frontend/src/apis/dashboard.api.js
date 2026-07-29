import http from './api'

export const getDashboardStatsApi = () => http.get('/v1/dashboard/stats')
