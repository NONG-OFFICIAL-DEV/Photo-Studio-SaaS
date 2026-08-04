import http from './api'

export const getBillingApi = () => http.get('/v1/billing')

export const getBillingPlansApi = () => http.get('/v1/billing/plans')

export const getBillingPaymentsApi = () => http.get('/v1/billing/payments')

export const renewBillingApi = billingCycle => http.post('/v1/billing/renew', { billing_cycle: billingCycle })

export const changeBillingPlanApi = (planId, billingCycle) =>
  http.put('/v1/billing/plan', { plan_id: planId, billing_cycle: billingCycle })

export const cancelBillingApi = () => http.post('/v1/billing/cancel')

export const resumeBillingApi = () => http.post('/v1/billing/resume')

export const submitPaymentClaimApi = ({ claimed_amount, note, receipt }) => {
  const formData = new FormData()
  if (claimed_amount !== null && claimed_amount !== undefined && claimed_amount !== '') {
    formData.append('claimed_amount', claimed_amount)
  }
  if (note) formData.append('note', note)
  if (receipt) formData.append('receipt', receipt)
  return http.post('/v1/billing/payment-claims', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
}
