import http from './api'

export const getServiceAddOnsApi = () => http.get('/v1/services/addons')

export const createServiceAddOnApi = payload => http.post('/v1/services/addons', payload)

export const updateServiceAddOnApi = (id, payload) => http.put(`/v1/services/addons/${id}`, payload)

export const deleteServiceAddOnApi = id => http.delete(`/v1/services/addons/${id}`)
