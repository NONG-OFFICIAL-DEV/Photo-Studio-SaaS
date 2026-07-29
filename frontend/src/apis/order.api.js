import http from './api'

export const getOrdersApi = params => http.get('/v1/orders', { params })

export const getOrderApi = id => http.get(`/v1/orders/${id}`)

export const createOrderApi = payload => http.post('/v1/orders', payload)

export const updateOrderApi = (id, payload) => http.put(`/v1/orders/${id}`, payload)

export const deleteOrderApi = id => http.delete(`/v1/orders/${id}`)

export const confirmOrderApi = id => http.post(`/v1/orders/${id}/confirm`)

export const startOrderProductionApi = (id, assignedUserId) =>
  http.post(`/v1/orders/${id}/start-production`, { assigned_user_id: assignedUserId })

export const readyOrderForDeliveryApi = id => http.post(`/v1/orders/${id}/ready-for-delivery`)

export const deliverOrderApi = id => http.post(`/v1/orders/${id}/deliver`)

export const cancelOrderApi = (id, reason) => http.post(`/v1/orders/${id}/cancel`, { reason })
