import http from './api'

export const getServicesApi = params => http.get('/v1/services', { params })

export const getServiceApi = id => http.get(`/v1/services/${id}`)

export const createServiceApi = payload => http.post('/v1/services', payload)

export const updateServiceApi = (id, payload) => http.put(`/v1/services/${id}`, payload)

export const deleteServiceApi = id => http.delete(`/v1/services/${id}`)
