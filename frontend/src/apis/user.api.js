import http from './api'

export const getUsersApi = () => http.get('/v1/users')

export const updateUserEmploymentApi = (id, payload) => http.put(`/v1/users/${id}`, payload)
