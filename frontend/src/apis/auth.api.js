import http from './api'

export const registerApi = payload => http.post('/v1/auth/register', payload)

export const loginApi = payload => http.post('/v1/auth/login', payload)

export const logoutApi = () => http.post('/v1/auth/logout')

export const refreshApi = () => http.post('/v1/auth/refresh')

export const meApi = () => http.get('/v1/auth/me')

export const forgotPasswordApi = payload => http.post('/v1/auth/password/forgot', payload)

export const resetPasswordApi = payload => http.post('/v1/auth/password/reset', payload)

export const resendVerificationApi = () => http.post('/v1/auth/email/resend')

export const updateEmailApi = payload => http.put('/v1/auth/email', payload)

export const updatePasswordApi = payload => http.put('/v1/auth/password', payload)

export const verifyTwoFactorApi = payload => http.post('/v1/auth/two-factor/verify', payload)

export const setupTwoFactorApi = () => http.post('/v1/auth/two-factor/setup')

export const confirmTwoFactorApi = payload => http.post('/v1/auth/two-factor/confirm', payload)

export const disableTwoFactorApi = payload => http.post('/v1/auth/two-factor/disable', payload)
