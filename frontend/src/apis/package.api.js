import http from './api'

export const getPackagesApi = params => http.get('/v1/packages', { params })

export const getPackageApi = id => http.get(`/v1/packages/${id}`)

export const createPackageApi = payload => http.post('/v1/packages', payload)

export const updatePackageApi = (id, payload) => http.put(`/v1/packages/${id}`, payload)

export const deletePackageApi = id => http.delete(`/v1/packages/${id}`)

export const sendPackageTelegramApi = (id, customerId, format = 'text') =>
  http.post(`/v1/packages/${id}/telegram/send`, { customer_id: customerId, format })

export const getPackageSummaryTextApi = id => http.get(`/v1/packages/${id}/summary-text`)

export const getPackageImageApi = id => http.get(`/v1/packages/${id}/image`, { responseType: 'blob' })
