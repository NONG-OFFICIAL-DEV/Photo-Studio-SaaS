import http from './api'

export const getBillingApi = () => http.get('/v1/billing')

export const getBillingPlansApi = () => http.get('/v1/billing/plans')

export const getBillingPaymentsApi = () => http.get('/v1/billing/payments')

export const renewBillingApi = billingCycle => http.post('/v1/billing/renew', { billing_cycle: billingCycle })

export const changeBillingPlanApi = planId => http.put('/v1/billing/plan', { plan_id: planId })

export const cancelBillingApi = () => http.post('/v1/billing/cancel')

export const resumeBillingApi = () => http.post('/v1/billing/resume')
