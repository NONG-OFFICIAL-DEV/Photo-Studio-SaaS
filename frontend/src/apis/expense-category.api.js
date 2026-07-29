import http from './api'

export const getExpenseCategoriesApi = () => http.get('/v1/expenses/categories')

export const createExpenseCategoryApi = payload => http.post('/v1/expenses/categories', payload)

export const updateExpenseCategoryApi = (id, payload) => http.put(`/v1/expenses/categories/${id}`, payload)

export const deleteExpenseCategoryApi = id => http.delete(`/v1/expenses/categories/${id}`)
