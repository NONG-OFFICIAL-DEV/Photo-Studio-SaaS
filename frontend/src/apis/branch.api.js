import http from './api'

export const getBranchesApi = params => http.get('/v1/branches', { params })

export const createBranchApi = payload => http.post('/v1/branches', payload)

export const updateBranchApi = (id, payload) => http.put(`/v1/branches/${id}`, payload)

export const deleteBranchApi = id => http.delete(`/v1/branches/${id}`)
