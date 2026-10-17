import { api } from '@/lib/api'
import type { BillingState, CheckoutResult, Interval, PublicPricing } from './types'

export async function fetchPublicPricing(): Promise<PublicPricing> {
  const { data } = await api.get<PublicPricing>('/pricing')
  return data
}

export async function fetchBilling(): Promise<BillingState> {
  const { data } = await api.get<BillingState>('/billing')
  return data
}

export async function checkoutPlan(planKey: string, interval: Interval): Promise<CheckoutResult> {
  const { data } = await api.post<CheckoutResult>('/billing/plans', { plan_key: planKey, interval })
  return data
}

export async function checkoutPack(packKey: string): Promise<CheckoutResult> {
  const { data } = await api.post<CheckoutResult>('/billing/packs', { pack_key: packKey })
  return data
}

export async function setCountry(countryCode: string): Promise<{ country_code: string; provider: 'flutterwave' | 'paddle' }> {
  const { data } = await api.post('/billing/country', { country_code: countryCode })
  return data
}

export async function cancelPlan(): Promise<{ message: string }> {
  const { data } = await api.post('/billing/cancel')
  return data
}
