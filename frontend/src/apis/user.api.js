import http from './api'

export const getUsersApi = (params = {}) => http.get('/v1/users', { params })

export const createUserApi = payload => http.post('/v1/users', payload)

export const updateUserEmploymentApi = (id, payload) => http.put(`/v1/users/${id}`, payload)

export const deactivateUserApi = id => http.post(`/v1/users/${id}/deactivate`)

export const reactivateUserApi = id => http.post(`/v1/users/${id}/reactivate`)
