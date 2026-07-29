import http from './api'

export const getPackagesApi = params => http.get('/v1/packages', { params })

export const getPackageApi = id => http.get(`/v1/packages/${id}`)

export const createPackageApi = payload => http.post('/v1/packages', payload)

export const updatePackageApi = (id, payload) => http.put(`/v1/packages/${id}`, payload)

export const deletePackageApi = id => http.delete(`/v1/packages/${id}`)
