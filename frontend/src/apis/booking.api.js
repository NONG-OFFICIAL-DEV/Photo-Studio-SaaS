import http from './api'

export const getBookingsApi = params => http.get('/v1/bookings', { params })

export const getBookingCalendarApi = params => http.get('/v1/bookings/calendar', { params })

export const getBookingApi = id => http.get(`/v1/bookings/${id}`)

export const createBookingApi = payload => http.post('/v1/bookings', payload)

export const updateBookingApi = (id, payload) => http.put(`/v1/bookings/${id}`, payload)

export const deleteBookingApi = id => http.delete(`/v1/bookings/${id}`)

export const confirmBookingApi = id => http.post(`/v1/bookings/${id}/confirm`)

export const startBookingApi = id => http.post(`/v1/bookings/${id}/start`)

export const completeBookingApi = id => http.post(`/v1/bookings/${id}/complete`)

export const noShowBookingApi = id => http.post(`/v1/bookings/${id}/no-show`)

export const cancelBookingApi = (id, reason) => http.post(`/v1/bookings/${id}/cancel`, { reason })
