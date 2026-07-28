import { http } from '@/services/http'
import { API_ENDPOINTS } from '@/constants/api-endpoints'

export const authService = {
  register(payload) {
    return http.post(API_ENDPOINTS.AUTH.REGISTER, payload)
  },
  login(payload) {
    return http.post(API_ENDPOINTS.AUTH.LOGIN, payload)
  },
  logout() {
    return http.post(API_ENDPOINTS.AUTH.LOGOUT)
  },
  refresh() {
    return http.post(API_ENDPOINTS.AUTH.REFRESH)
  },
  me() {
    return http.get(API_ENDPOINTS.AUTH.ME)
  },
  forgotPassword(payload) {
    return http.post(API_ENDPOINTS.AUTH.FORGOT_PASSWORD, payload)
  },
  resetPassword(payload) {
    return http.post(API_ENDPOINTS.AUTH.RESET_PASSWORD, payload)
  },
  resendVerification() {
    return http.post(API_ENDPOINTS.AUTH.RESEND_VERIFICATION)
  },
}
