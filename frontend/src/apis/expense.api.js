import http from './api'

export const getExpensesApi = params => http.get('/v1/expenses', { params })

export const getExpenseApi = id => http.get(`/v1/expenses/${id}`)

export const createExpenseApi = payload => http.post('/v1/expenses', payload)

export const updateExpenseApi = (id, payload) => http.put(`/v1/expenses/${id}`, payload)

export const deleteExpenseApi = id => http.delete(`/v1/expenses/${id}`)
