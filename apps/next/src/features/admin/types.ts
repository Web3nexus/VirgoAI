export type DashboardRange = 7 | 30 | 90

export type AdminOverview = {
  range: DashboardRange
  margin_percent: number
  kept_share_percent: number
  kpis: {
    revenue_usd: number
    revenue_prev_usd: number
    cost_usd: number
    earned_usd: number
    earned_prev_usd: number
    runs: number
    runs_prev: number
    success_rate: number | null
    signups: number
    signups_prev: number
  }
  series: { date: string; revenue: number; cost: number; earned: number; runs: number; failed: number; signups: number }[]
  by_tool: { tool: string; runs: number; revenue_usd: number; cost_usd: number; earned_usd: number }[]
  by_model: { model: string; runs: number; revenue_usd: number }[]
  status: { status: 'succeeded' | 'failed' | 'running'; count: number }[]
  recent_runs: {
    id: string
    tool: string
    model_key: string | null
    status: string
    user: string | null
    price_usd: number
    created_at: string | null
  }[]
  revenue_usd: number
  provider_cost_usd: number
  earned_usd: number
  runs: { total: number; succeeded: number; failed: number; running: number }
  users: { total: number; suspended: number }
  credit_held_usd: number
  credit_granted_usd: number
}

export type CredentialRow = {
  name: string
  label: string
  source: 'database' | 'environment' | 'missing'
  configured: boolean
  last4: string | null
  updated_at: string | null
}

export type AdminUserRow = {
  id: number
  name: string
  email: string
  role: string
  suspended: boolean
  deleted: boolean
  balance_usd: number
  runs: number
}

export type UserStatusFilter = 'active' | 'suspended' | 'deleted'

export type Paginated<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; total?: number }
}

export type AdminUserDetail = AdminUserRow & {
  created_at: string | null
  totals: { succeeded: number; failed: number; paid_usd: number }
  recent_runs: {
    id: string
    tool: string
    model_key: string | null
    status: string
    price_usd: number
    created_at: string | null
  }[]
  ledger: {
    id: string
    type: 'grant' | 'charge' | 'refund' | 'settlement' | 'adjustment'
    amount_usd: number
    balance_after_usd: number
    note: string | null
    created_at: string | null
  }[]
}

export type ModelPriceRow = {
  id: number
  provider_key: string
  model_key: string
  unit: string
  cost_usd: number
  sell_usd: number
  source: 'fal_api' | 'anthropic_docs' | 'admin'
  verified_at: string | null
}

export type PersonalPromoConfig = {
  enabled: boolean
  code: string
  percent: number
  window_hours: number
}

export type PricingResponse = {
  margin_percent: number
  kept_share_percent: number
  promo: PersonalPromoConfig
  prices: ModelPriceRow[]
}

export type PricingSyncResponse = {
  fal: { upserted: number; skipped_admin: number; errors: string[] } | null
  anthropic: { upserted: number; skipped_admin: number }
  errors: string[]
}

export type AuditRow = {
  id: number
  actor: string | null
  action: string
  subject: string | null
  details: Record<string, unknown>
  created_at: string | null
}

export type ProviderModelRow = {
  id: number
  key: string
  name: string
  enabled: boolean
  priority: number
  capabilities: string[]
}

export type ProviderRow = {
  /** Null until the provider has been synced once. */
  id: number | null
  key: string
  name: string
  status: 'active' | 'paused' | 'disabled' | 'not_synced'
  priority: number
  /** True when the key is set in the environment. The key itself is never sent. */
  key_configured: boolean
  adapter_registered: boolean
  runtime_ready: boolean
  credential_name: string | null
  models: ProviderModelRow[]
}

export type EmailTemplateRow = {
  id: number
  key: string
  name: string
  subject: string
  body: string
  enabled: boolean
}

export type EmailTemplatesResponse = {
  data: EmailTemplateRow[]
  placeholders: string[]
  from: { name: string; address: string }
  mailer: string
}

export type EmailLogRow = {
  id: number
  template_key: string | null
  to: string
  subject: string
  status: 'sent' | 'failed' | 'skipped'
  error: string | null
  created_at: string | null
}

export type SystemSettings = {
  registration_enabled: boolean
  welcome_credit_usd: number
  maintenance_mode: boolean
  maintenance_message: string
  email_from_name: string
  email_from_address: string
  support_email: string
  mail_driver: 'log' | 'smtp'
  mail_host: string
  mail_port: number
  mail_username: string
  mail_encryption: 'tls' | 'ssl' | 'none'
}

export type SecretStatus = {
  name: string
  label: string
  source: 'database' | 'environment' | 'missing'
  configured: boolean
  last4: string | null
  updated_at: string | null
}

export type SystemResponse = {
  mail_password: SecretStatus
  settings: SystemSettings
  runtime: {
    queue_connection: string
    mail_mailer: string
    margin_percent: number
    anthropic_ready: boolean
    fal_ready: boolean
    generation_max_wait_seconds: number
    fal_poll_interval_seconds: number
    anthropic_max_tokens: number
    app_env: string
  }
}

export type PlanRow = {
  id: number
  key: string
  name: string
  tagline: string | null
  monthly_usd: number
  yearly_usd: number
  monthly_credit_usd: number
  features: string[]
  highlighted: boolean
  active: boolean
  sort: number
  paddle_monthly_price_id: string | null
  paddle_yearly_price_id: string | null
  flutterwave_monthly_plan_id: number | null
  flutterwave_yearly_plan_id: number | null
}

export type PlanEconomics = {
  key: string
  name: string
  monthly_price_usd: number
  yearly_price_usd: number
  monthly_credit_usd: number
  monthly: { revenue_usd: number; provider_budget_usd: number; kept_usd: number }
  yearly: { revenue_usd: number; provider_budget_usd: number; kept_usd: number }
  active: boolean
  subscribers: number
}

export type PackRow = {
  id: number
  key: string
  name: string
  credits_usd: number
  price_usd: number
  subscriber_discount_percent: number
  subscriber_price_usd: number
  active: boolean
  sort: number
  paddle_price_id: string | null
  paddle_subscriber_price_id: string | null
}

export type BillingAdmin = {
  economics: PlanEconomics[]
  plans: PlanRow[]
  packs: PackRow[]
  subscriptions: { active: number; past_due: number; ended: number }
  pending_purchases: number
}

export type PurchaseRowAdmin = {
  id: string
  user: string | null
  kind: 'subscription' | 'subscription_renewal' | 'credit_pack'
  item_key: string
  interval: 'monthly' | 'yearly' | null
  amount_usd: number
  credits_usd: number
  status: 'pending' | 'paid' | 'failed'
  provider: string
  paid_at: string | null
  created_at: string | null
}

export type SubscriptionRowAdmin = {
  id: string
  user: string | null
  plan_key: string
  interval: 'monthly' | 'yearly'
  status: 'active' | 'past_due' | 'ended'
  entitled: boolean
  current_period_end: string
  cancel_at_period_end: boolean
}

export type TeamMember = { id: number; name: string; email: string; suspended: boolean }

export type PaymentsStatus = {
  credentials: SecretStatus[]
  webhooks: {
    flutterwave: { url: string; secret_name: string }
    paddle: { url: string; secret_name: string }
  }
  paddle_base: string
  recent_events: { provider: string; type: string; outcome: string; at: string | null }[]
  plans: { key: string; flutterwave: boolean; paddle: boolean }[]
}
