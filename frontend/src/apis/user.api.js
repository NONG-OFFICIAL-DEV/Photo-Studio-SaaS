import http from './api'

export const getUsersApi = () => http.get('/v1/users')
