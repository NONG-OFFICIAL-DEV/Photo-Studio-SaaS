import http from './api'

export const getPayrollEntriesApi = params => http.get('/v1/payroll-entries', { params })

export const getPayrollEntryApi = id => http.get(`/v1/payroll-entries/${id}`)

export const createPayrollEntryApi = payload => http.post('/v1/payroll-entries', payload)

export const updatePayrollEntryApi = (id, payload) => http.put(`/v1/payroll-entries/${id}`, payload)

export const deletePayrollEntryApi = id => http.delete(`/v1/payroll-entries/${id}`)

export const payPayrollEntryApi = id => http.post(`/v1/payroll-entries/${id}/pay`)
