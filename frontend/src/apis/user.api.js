import http from './api'

export const getUsersApi = () => http.get('/v1/users')

export const createUserApi = payload => http.post('/v1/users', payload)

export const updateUserEmploymentApi = (id, payload) => http.put(`/v1/users/${id}`, payload)
