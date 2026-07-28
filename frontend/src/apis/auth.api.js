import http from './api'

export const registerApi = payload => http.post('/v1/auth/register', payload)

export const loginApi = payload => http.post('/v1/auth/login', payload)

export const logoutApi = () => http.post('/v1/auth/logout')

export const refreshApi = () => http.post('/v1/auth/refresh')

export const meApi = () => http.get('/v1/auth/me')

export const forgotPasswordApi = payload => http.post('/v1/auth/password/forgot', payload)

export const resetPasswordApi = payload => http.post('/v1/auth/password/reset', payload)

export const resendVerificationApi = () => http.post('/v1/auth/email/resend')
