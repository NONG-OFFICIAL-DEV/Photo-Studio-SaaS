import http from './api'

export const getCustomersApi = params => http.get('/v1/customers', { params })

export const getCustomerApi = id => http.get(`/v1/customers/${id}`)

export const createCustomerApi = payload => http.post('/v1/customers', payload)

export const updateCustomerApi = (id, payload) => http.put(`/v1/customers/${id}`, payload)

export const deleteCustomerApi = id => http.delete(`/v1/customers/${id}`)

export const toggleCustomerFavoriteApi = id => http.post(`/v1/customers/${id}/favorite`)

export const blacklistCustomerApi = (id, reason) => http.post(`/v1/customers/${id}/blacklist`, { reason })

export const unblacklistCustomerApi = id => http.post(`/v1/customers/${id}/unblacklist`)

export const addCustomerNoteApi = (id, note) => http.post(`/v1/customers/${id}/notes`, { note })

export const deleteCustomerNoteApi = (id, noteId) => http.delete(`/v1/customers/${id}/notes/${noteId}`)

export const exportCustomersApi = (format, filters = {}) =>
  http.get('/v1/customers/export', { params: { format, ...filters }, responseType: 'blob' })

export const importCustomersApi = (file) => {
  const formData = new FormData()
  formData.append('file', file)
  return http.post('/v1/customers/import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export const getCustomerTelegramLinkApi = id => http.post(`/v1/customers/${id}/telegram/link`)

export const unlinkCustomerTelegramApi = id => http.post(`/v1/customers/${id}/telegram/unlink`)

export const sendCustomerTelegramFilesApi = (id, files, caption) => {
  const formData = new FormData()
  files.forEach(file => formData.append('files[]', file))
  if (caption) formData.append('caption', caption)
  return http.post(`/v1/customers/${id}/telegram/send`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export const getCustomerTelegramActivityApi = (id, params = {}) => http.get(`/v1/customers/${id}/telegram/activity`, { params })
