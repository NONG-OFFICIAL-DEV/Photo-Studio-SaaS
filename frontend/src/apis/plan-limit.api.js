import http from './api'

export const getPlanLimitsApi = () => http.get('/v1/plan-limits')
