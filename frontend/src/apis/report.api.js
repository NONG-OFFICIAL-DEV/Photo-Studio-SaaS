import http from './api'

export const getRevenueReportApi = params => http.get('/v1/reports/revenue', { params })

export const getBookingsReportApi = params => http.get('/v1/reports/bookings', { params })

export const getOrdersReportApi = params => http.get('/v1/reports/orders', { params })

export const getExpenseReportApi = params => http.get('/v1/reports/expenses', { params })

export const exportRevenueReportApi = params => http.get('/v1/reports/revenue/export', { params, responseType: 'blob' })

export const exportBookingsReportApi = params => http.get('/v1/reports/bookings/export', { params, responseType: 'blob' })

export const exportOrdersReportApi = params => http.get('/v1/reports/orders/export', { params, responseType: 'blob' })

export const exportExpenseReportApi = params => http.get('/v1/reports/expenses/export', { params, responseType: 'blob' })
