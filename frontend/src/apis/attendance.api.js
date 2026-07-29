import http from './api'

export const getAttendanceRecordsApi = params => http.get('/v1/attendance', { params })

export const getTodayAttendanceApi = () => http.get('/v1/attendance/today')

export const clockInApi = () => http.post('/v1/attendance/clock-in')

export const clockOutApi = () => http.post('/v1/attendance/clock-out')

export const createAttendanceRecordApi = payload => http.post('/v1/attendance', payload)

export const updateAttendanceRecordApi = (id, payload) => http.put(`/v1/attendance/${id}`, payload)

export const deleteAttendanceRecordApi = id => http.delete(`/v1/attendance/${id}`)
