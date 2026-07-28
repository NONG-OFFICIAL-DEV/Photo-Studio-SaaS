import http from './api'

export const getCustomerTagsApi = () => http.get('/v1/customers/tags')

export const createCustomerTagApi = payload => http.post('/v1/customers/tags', payload)

export const updateCustomerTagApi = (id, payload) => http.put(`/v1/customers/tags/${id}`, payload)

export const deleteCustomerTagApi = id => http.delete(`/v1/customers/tags/${id}`)
