export type Interval = 'monthly' | 'yearly'

export type PublicPlan = {
  key: string
  name: string
  tagline: string | null
  highlighted: boolean
  monthly_usd: number
  yearly_usd: number
  yearly_per_month_usd: number
  yearly_savings_percent: number
  /** Tokens added each month. */
  monthly_tokens: number
  /** Tokens across a full year of a yearly plan. */
  yearly_tokens: number
  features: string[]
}

export type PublicPack = {
  key: string
  name: string
  tokens: number
  price_usd: number
  subscriber_price_usd: number
  subscriber_discount_percent: number
}

export type PublicPricing = {
  currency: string
  plans: PublicPlan[]
  packs: PublicPack[]
  intervals: Interval[]
}

export type Provider = 'flutterwave' | 'paddle'

export type BillingPurchase = {
  id: string
  kind: 'subscription' | 'subscription_renewal' | 'credit_pack'
  item_key: string
  interval: Interval | null
  provider: string
  amount_usd: number
  tokens: number
  status: 'pending' | 'paid' | 'failed'
  checkout_url: string | null
  created_at: string | null
}

export type CheckoutResult = { purchase: BillingPurchase; checkout_url: string; message: string }

export type BillingState = {
  balance_tokens: number
  subscription_tokens: number
  purchased_tokens: number
  country_code: string | null
  suggested_country: string | null
  /** The provider the country routes to, or null until a country is set. */
  provider: 'flutterwave' | 'paddle' | null
  subscription: {
    plan_key: string
    plan_name: string | null
    interval: Interval
    status: 'active' | 'past_due' | 'ended'
    entitled: boolean
    current_period_end: string
    next_credit_at: string
    cancel_at_period_end: boolean
  } | null
  purchases: BillingPurchase[]
}
