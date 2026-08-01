import http from './api'

export const getSettingsApi = () => http.get('/v1/settings')

export const updateSettingsApi = (payload) => http.put('/v1/settings', payload)

export const uploadSettingsLogoApi = (file) => {
  const formData = new FormData()
  formData.append('logo', file)
  return http.post('/v1/settings/logo', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}

export const uploadSettingsQrPaymentApi = (file) => {
  const formData = new FormData()
  formData.append('qr_payment', file)
  return http.post('/v1/settings/qr-payment', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}

export const exportSettingsDataApi = () => http.get('/v1/settings/export', { responseType: 'blob' })

export const connectTelegramApi = (botToken) => http.post('/v1/settings/telegram/connect', { bot_token: botToken })

export const disconnectTelegramApi = () => http.post('/v1/settings/telegram/disconnect')
