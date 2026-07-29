import http from './api'

export const getServiceCategoriesApi = () => http.get('/v1/services/categories')

export const createServiceCategoryApi = payload => http.post('/v1/services/categories', payload)

export const updateServiceCategoryApi = (id, payload) => http.put(`/v1/services/categories/${id}`, payload)

export const deleteServiceCategoryApi = id => http.delete(`/v1/services/categories/${id}`)
