import http from './api'

export const getCommissionEntriesApi = params => http.get('/v1/commission-entries', { params })

export const createCommissionEntryApi = payload => http.post('/v1/commission-entries', payload)

export const updateCommissionEntryApi = (id, payload) => http.put(`/v1/commission-entries/${id}`, payload)

export const deleteCommissionEntryApi = id => http.delete(`/v1/commission-entries/${id}`)
