import http from './api'

export const getSettingsApi = () => http.get('/v1/settings')

export const updateSettingsApi = (payload) => http.put('/v1/settings', payload)

export const uploadSettingsLogoApi = (file) => {
  const formData = new FormData()
  formData.append('logo', file)
  return http.post('/v1/settings/logo', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}

export const exportSettingsDataApi = () => http.get('/v1/settings/export', { responseType: 'blob' })
