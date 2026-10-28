import { HugeiconsIcon } from '@hugeicons/react'
import { CheckmarkCircle02Icon } from '@hugeicons/core-free-icons'
import { cn } from '@/lib/utils'
import { usd } from '@/lib/money'
import type { Interval, PlanOffer, PublicPlan } from '@/features/billing/types'

const NUMBER = new Intl.NumberFormat('en-US')

/** One plan in the pricing table. The highlighted plan gets the glow and the badge. An offer shows the discounted price beside the list price. */
export function PlanCard({
  plan,
  interval,
  action,
  offer,
}: {
  plan: PublicPlan
  interval: Interval
  action: React.ReactNode
  offer?: PlanOffer | null
}) {
  const yearly = interval === 'yearly'
  const listPrice = yearly ? plan.yearly_per_month_usd : plan.monthly_usd
  const price = offer ? (yearly ? offer.yearly_per_month_usd : offer.monthly_usd) : listPrice

  return (
    <div
      className={cn(
        'relative flex flex-col rounded-3xl border p-7 transition-transform duration-300 hover:-translate-y-1',
        plan.highlighted
          ? 'border-transparent bg-gradient-to-b from-brand/25 via-card/90 to-card shadow-[0_0_80px_-20px_var(--brand-glow)] ring-1 ring-brand/50'
          : 'border-border bg-card/70 backdrop-blur-[14px]',
      )}
    >
      {plan.highlighted && (
        <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand px-3 py-1 text-xs font-medium text-white shadow-lg">
          Most popular
        </span>
      )}

      <div>
        <h3 className="font-heading text-xl font-semibold">{plan.name}</h3>
        <p className="mt-1 min-h-10 text-sm text-muted-foreground">{plan.tagline}</p>
      </div>

      <div className="mt-6">
        <div className="flex items-end gap-2">
          {offer && (
            <span className="pb-3 text-lg text-muted-foreground line-through decoration-muted-foreground/70">
              {usd(listPrice, listPrice % 1 === 0 ? 0 : 2)}
            </span>
          )}
          <span className="font-heading text-5xl font-semibold tracking-tight">{usd(price, price % 1 === 0 ? 0 : 2)}</span>
          <span className="pb-2 text-sm text-muted-foreground">/ month</span>
        </div>
        <p className="mt-2 min-h-5 text-xs text-muted-foreground">
          {offer && <span className="mr-1 font-medium text-emerald-300">{offer.percent}% off ·</span>}
          {yearly ? (
            <>
              Billed {usd(offer ? offer.yearly_usd : plan.yearly_usd, 0)} yearly ·{' '}
              <span className="text-emerald-300">save {plan.yearly_savings_percent}%</span>
            </>
          ) : (
            'Billed monthly. Cancel any time.'
          )}
        </p>
      </div>

      <div className="mt-6 rounded-2xl border border-border/60 bg-background/40 p-4">
        <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">
          {yearly ? 'Each month you get' : 'Every month you get'}
        </p>
        <p className="mt-1 font-heading text-2xl font-semibold">{NUMBER.format(plan.monthly_tokens)} tokens</p>
        {yearly && <p className="text-xs text-muted-foreground">{NUMBER.format(plan.yearly_tokens)} tokens across the year</p>}
      </div>

      <ul className="mt-6 flex-1 space-y-3 text-sm">
        {plan.features.map((feature) => (
          <li key={feature} className="flex items-start gap-2.5">
            <HugeiconsIcon icon={CheckmarkCircle02Icon} size={18} strokeWidth={1.75} className="mt-0.5 shrink-0 text-emerald-300" aria-hidden />
            <span>{feature}</span>
          </li>
        ))}
      </ul>

      <div className="mt-8">{action}</div>
    </div>
  )
}

