import http from './api'

export const getInventoryItemsApi = params => http.get('/v1/inventory-items', { params })

export const getInventoryItemApi = id => http.get(`/v1/inventory-items/${id}`)

export const createInventoryItemApi = payload => http.post('/v1/inventory-items', payload)

export const updateInventoryItemApi = (id, payload) => http.put(`/v1/inventory-items/${id}`, payload)

export const deleteInventoryItemApi = id => http.delete(`/v1/inventory-items/${id}`)

export const recordInventoryMovementApi = (id, payload) => http.post(`/v1/inventory-items/${id}/movements`, payload)

export const deleteInventoryMovementApi = (id, movementId) => http.delete(`/v1/inventory-items/${id}/movements/${movementId}`)
