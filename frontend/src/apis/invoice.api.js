import http from './api'

export const getInvoicesApi = params => http.get('/v1/invoices', { params })

export const getInvoiceApi = id => http.get(`/v1/invoices/${id}`)

export const createInvoiceApi = payload => http.post('/v1/invoices', payload)

export const updateInvoiceApi = (id, payload) => http.put(`/v1/invoices/${id}`, payload)

export const deleteInvoiceApi = id => http.delete(`/v1/invoices/${id}`)

export const sendInvoiceApi = id => http.post(`/v1/invoices/${id}/send`)

export const getInvoicePdfApi = id => http.get(`/v1/invoices/${id}/pdf`, { responseType: 'blob' })

export const getInvoiceShareLinkApi = id => http.get(`/v1/invoices/${id}/share-link`)

export const voidInvoiceApi = (id, reason) => http.post(`/v1/invoices/${id}/void`, { reason })

export const recordInvoicePaymentApi = (id, payload) => http.post(`/v1/invoices/${id}/payments`, payload)

export const deleteInvoicePaymentApi = (id, paymentId) => http.delete(`/v1/invoices/${id}/payments/${paymentId}`)
