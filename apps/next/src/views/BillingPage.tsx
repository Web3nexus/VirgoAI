import { useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { PlanCard } from '@/components/billing/PlanCard'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useBilling, useCancelPlan, useCheckoutPack, useCheckoutPlan, usePublicPricing, useSetCountry } from '@/features/billing/hooks'
import type { BillingPurchase, Interval } from '@/features/billing/types'
import { COUNTRIES, countryName } from '@/lib/countries'
import { formatTime } from '@/features/workflows/timeline'
import { cn } from '@/lib/utils'

const STATUS_LABEL: Record<string, string> = {
  active: 'Active',
  past_due: 'Renewal needed',
  ended: 'Ended',
}

const PROVIDER_NOTE: Record<string, string> = {
  flutterwave: 'Payments in your region are taken securely through Flutterwave.',
  paddle: 'Payments are taken securely through Paddle, which handles invoices and tax.',
}

const NUMBER = new Intl.NumberFormat('en-US')

/** Tokens: 1 token = $0.001 of usage. Shown in whole tokens, with the fraction kept for small balances. */
function tokens(value: number): string {
  return `${NUMBER.format(Math.floor(value * 10) / 10)} tokens`
}

export function BillingPage() {
  const { data: billing, isLoading, isError } = useBilling()
  const { data: pricing } = usePublicPricing()
  const [interval, setInterval] = useState<Interval>('yearly')
  const checkoutPlan = useCheckoutPlan()
  const checkoutPack = useCheckoutPack()
  const cancel = useCancelPlan()

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !billing) return <p className="text-sm text-red-300">Billing could not be loaded.</p>

  const total = billing.balance_tokens || 0
  const planShare = total > 0 ? (billing.subscription_tokens / total) * 100 : 0

  return (
    <div className="space-y-8">
      <PageHeader title="Billing" description="Your tokens, your plan, and your payments." />

      {!billing.country_code && <CountryStep suggested={billing.suggested_country} />}

      <section aria-label="Tokens" className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <Card className="glass overflow-hidden border-border/60">
          <CardContent className="space-y-6 p-6">
            <div>
              <p className="text-xs text-muted-foreground">Your tokens</p>
              <p className="mt-1 font-heading text-5xl font-semibold tracking-tight">{NUMBER.format(total)}</p>
              <p className="text-xs text-muted-foreground">1 token = $0.001 of usage</p>
            </div>
            <div className="space-y-2">
              <div className="flex h-2.5 overflow-hidden rounded-full bg-card">
                <div className="h-full bg-gradient-to-r from-brand to-neon-cyan transition-all" style={{ width: `${planShare}%` }} />
                <div className="h-full bg-emerald-400/70 transition-all" style={{ width: `${100 - planShare}%` }} />
              </div>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="flex items-center gap-2"><span className="size-2 rounded-full bg-brand" aria-hidden /> From your plan</p>
                  <p className="font-medium">{tokens(billing.subscription_tokens)}</p>
                  <p className="text-xs text-muted-foreground">Renews each month. Used first.</p>
                </div>
                <div>
                  <p className="flex items-center gap-2"><span className="size-2 rounded-full bg-emerald-400" aria-hidden /> Top-ups</p>
                  <p className="font-medium">{tokens(billing.purchased_tokens)}</p>
                  <p className="text-xs text-muted-foreground">Never expire.</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="space-y-4 p-6">
            <div className="flex items-center justify-between">
              <p className="text-xs text-muted-foreground">Your plan</p>
              {billing.subscription && (
                <Badge variant={billing.subscription.status === 'active' ? 'default' : 'destructive'}>
                  {billing.subscription.cancel_at_period_end ? 'Ending' : STATUS_LABEL[billing.subscription.status]}
                </Badge>
              )}
            </div>
            {billing.subscription ? (
              <>
                <p className="font-heading text-2xl font-semibold">{billing.subscription.plan_name}</p>
                <p className="text-sm text-muted-foreground">
                  {billing.subscription.interval === 'yearly' ? 'Yearly' : 'Monthly'} · next top-up of plan tokens {formatTime(billing.subscription.next_credit_at)} · period ends {formatTime(billing.subscription.current_period_end)}
                </p>
                {billing.subscription.status === 'past_due' && (
                  <p className="text-sm text-amber-300">Your period ended without a renewal. Renew below to restore your monthly tokens.</p>
                )}
                {billing.subscription.status === 'active' && !billing.subscription.cancel_at_period_end && (
                  <Button variant="outline" size="sm" disabled={cancel.isPending} onClick={() => cancel.mutate()}>
                    Cancel at period end
                  </Button>
                )}
              </>
            ) : (
              <p className="text-sm text-muted-foreground">No plan yet. Choose one below, or buy top-ups on their own.</p>
            )}
            {billing.provider && <p className="text-xs text-muted-foreground">{PROVIDER_NOTE[billing.provider]}</p>}
          </CardContent>
        </Card>
      </section>

      {pricing && billing.country_code && (
        <section aria-label="Plans" className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <h2 className="font-heading text-xl font-semibold">Plans</h2>
            <div role="radiogroup" aria-label="Billing period" className="inline-flex rounded-full border border-border p-1">
              {(['monthly', 'yearly'] as const).map((value) => (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={interval === value}
                  onClick={() => setInterval(value)}
                  className={cn('rounded-full px-4 py-1.5 text-sm transition-colors', interval === value ? 'bg-foreground text-background' : 'text-muted-foreground')}
                >
                  {value === 'monthly' ? 'Monthly' : 'Yearly'}
                </button>
              ))}
            </div>
          </div>
          <div className="grid gap-6 md:grid-cols-3">
            {pricing.plans.map((plan) => (
              <PlanCard
                key={plan.key}
                plan={plan}
                interval={interval}
                offer={billing.promo?.plans[plan.key] ? { percent: billing.promo.percent, ...billing.promo.plans[plan.key] } : null}
                action={
                  <Button
                    className="w-full"
                    variant={plan.highlighted ? 'default' : 'outline'}
                    disabled={checkoutPlan.isPending}
                    onClick={() => checkoutPlan.mutate({ planKey: plan.key, interval })}
                  >
                    {billing.subscription?.plan_key === plan.key ? 'Renew or change' : `Choose ${plan.name}`}
                  </Button>
                }
              />
            ))}
          </div>
        </section>
      )}

      {pricing && billing.country_code && pricing.packs.length > 0 && (
        <section aria-label="Top-ups" className="space-y-4">
          <h2 className="font-heading text-xl font-semibold">Top up tokens</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {pricing.packs.map((pack) => {
              const subscribed = billing.subscription?.entitled ?? false
              return (
                <Card key={pack.key} className="glass border-border/60">
                  <CardContent className="flex items-center justify-between gap-4 p-5">
                    <div>
                      <p className="font-heading text-xl font-semibold">{NUMBER.format(pack.tokens)} tokens</p>
                      <p className="text-xs text-muted-foreground">
                        {subscribed ? `$${pack.subscriber_price_usd.toFixed(0)} for you` : `$${pack.price_usd.toFixed(0)}`}
                        {pack.subscriber_discount_percent > 0 && !subscribed && ` · $${pack.subscriber_price_usd.toFixed(0)} with a plan`}
                      </p>
                    </div>
                    <Button variant="outline" disabled={checkoutPack.isPending} onClick={() => checkoutPack.mutate({ packKey: pack.key })}>
                      Buy
                    </Button>
                  </CardContent>
                </Card>
              )
            })}
          </div>
        </section>
      )}

      <section aria-label="Purchases" className="space-y-3">
        <h2 className="font-heading text-xl font-semibold">Purchases</h2>
        {billing.purchases.length === 0 ? (
          <p className="text-sm text-muted-foreground">Nothing bought yet.</p>
        ) : (
          <ul className="divide-y divide-border/60 rounded-xl border border-border/60">
            {billing.purchases.map((purchase) => (
              <PurchaseRow key={purchase.id} purchase={purchase} />
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

/** Asked once. The server suggests the country from the request, and the customer confirms it. */
function CountryStep({ suggested }: { suggested: string | null }) {
  const [code, setCode] = useState(suggested ?? 'US')
  const save = useSetCountry()

  return (
    <Card className="glass border-border/60">
      <CardContent className="flex flex-wrap items-end gap-4 p-5">
        <div className="min-w-64 flex-1 space-y-2">
          <label htmlFor="billing-country" className="text-sm font-medium">Where are you based?</label>
          <p className="text-xs text-muted-foreground">This decides how you pay. You only need to set it once.</p>
          <select
            id="billing-country"
            className="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm"
            value={code}
            onChange={(event) => setCode(event.target.value)}
          >
            {COUNTRIES.map((country) => (
              <option key={country.code} value={country.code}>{country.name}</option>
            ))}
          </select>
        </div>
        <Button disabled={save.isPending} onClick={() => save.mutate(code)}>Save country</Button>
      </CardContent>
    </Card>
  )
}

function PurchaseRow({ purchase }: { purchase: BillingPurchase }) {
  const label = purchase.kind === 'credit_pack' ? 'Top-up' : purchase.kind === 'subscription_renewal' ? 'Renewal' : 'Plan'
  return (
    <li className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
      <span className="font-medium">{label} · {purchase.item_key}{purchase.interval ? ` · ${purchase.interval}` : ''}</span>
      <span className="text-muted-foreground">${purchase.amount_usd.toFixed(2)} → {tokens(purchase.tokens)}</span>
      <Badge variant={purchase.status === 'failed' ? 'destructive' : 'outline'} className="capitalize">{purchase.status}</Badge>
      {purchase.status === 'pending' && purchase.checkout_url && (
        <a href={purchase.checkout_url} className="text-xs text-foreground underline underline-offset-4">Continue to payment</a>
      )}
      <span className="text-xs text-muted-foreground">{purchase.created_at ? formatTime(purchase.created_at) : '—'}</span>
    </li>
  )
}

export { countryName }
