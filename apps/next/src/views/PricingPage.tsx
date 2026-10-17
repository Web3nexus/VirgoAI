import { useState } from 'react'
import { Link } from '@/lib/router'
import { HugeiconsIcon } from '@hugeicons/react'
import { AiSparklesIcon, ArrowRight01Icon } from '@hugeicons/core-free-icons'
import { PlanCard } from '@/components/billing/PlanCard'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { usePublicPricing } from '@/features/billing/hooks'
import type { Interval } from '@/features/billing/types'
import { usd } from '@/lib/money'
import { cn } from '@/lib/utils'

/** The public pricing page. No sign-in needed. Shows what each plan buys, never the margin. */
export function PricingPage() {
  const [interval, setInterval] = useState<Interval>('yearly')
  const { data, isLoading, isError } = usePublicPricing()

  const bestSaving = Math.max(0, ...(data?.plans.map((plan) => plan.yearly_savings_percent) ?? [0]))

  return (
    <div className="relative min-h-svh overflow-hidden bg-background text-foreground">
      <div aria-hidden className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,var(--brand-glow),transparent)]" />
      <div aria-hidden className="pointer-events-none absolute -top-40 left-1/2 size-[46rem] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl" />

      <header className="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
        <Link to="/" className="font-heading text-lg font-semibold tracking-tight">virgoAi</Link>
        <div className="flex items-center gap-2">
          <Button variant="ghost" nativeButton={false} render={<Link to="/login" />}>Sign in</Button>
          <Button nativeButton={false} render={<Link to="/register" />}>Start free</Button>
        </div>
      </header>

      <main className="relative mx-auto max-w-6xl px-6 pb-24 pt-10">
        <section className="text-center">
          <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-3 py-1 text-xs text-muted-foreground">
            <HugeiconsIcon icon={AiSparklesIcon} size={14} strokeWidth={1.75} aria-hidden />
            Produce videos from a prompt. Pay for what you make.
          </span>
          <h1 className="mx-auto mt-6 max-w-3xl font-heading text-5xl font-semibold tracking-tight sm:text-6xl">
            A studio plan for every <span className="bg-gradient-to-r from-brand to-neon-cyan bg-clip-text text-transparent">production</span>
          </h1>
          <p className="mx-auto mt-5 max-w-xl text-muted-foreground">
            Each plan adds tokens every month, spent on images, video, scripts and clips. Top up whenever you need more.
          </p>

          <div role="radiogroup" aria-label="Billing period" className="mx-auto mt-10 inline-flex rounded-full border border-border bg-card/70 p-1 backdrop-blur-[14px]">
            {(['monthly', 'yearly'] as const).map((value) => (
              <button
                key={value}
                type="button"
                role="radio"
                aria-checked={interval === value}
                onClick={() => setInterval(value)}
                className={cn(
                  'flex items-center gap-2 rounded-full px-5 py-2 text-sm transition-all',
                  interval === value ? 'bg-foreground text-background shadow' : 'text-muted-foreground hover:text-foreground',
                )}
              >
                {value === 'monthly' ? 'Monthly' : 'Yearly'}
                {value === 'yearly' && bestSaving > 0 && (
                  <span className="rounded-full bg-emerald-400/20 px-2 py-0.5 text-[10px] text-emerald-600 dark:text-emerald-300">
                    save up to {bestSaving}%
                  </span>
                )}
              </button>
            ))}
          </div>
        </section>

        <section className="mt-14">
          {isLoading ? (
            <div className="grid gap-6 md:grid-cols-3">
              {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-[520px] rounded-3xl" />)}
            </div>
          ) : isError || !data ? (
            <p className="text-center text-muted-foreground">Pricing could not be loaded. Try again shortly.</p>
          ) : (
            <div className="grid items-stretch gap-6 pt-3 md:grid-cols-3">
              {data.plans.map((plan) => (
                <PlanCard
                  key={plan.key}
                  plan={plan}
                  interval={interval}
                  action={
                    <Button
                      className={cn('w-full gap-2', plan.highlighted && 'shadow-[0_10px_30px_-10px_var(--brand-glow)]')}
                      variant={plan.highlighted ? 'default' : 'outline'}
                      nativeButton={false} render={<Link to={`/register?plan=${plan.key}&interval=${interval}`} />}
                    >
                      Choose {plan.name}
                      <HugeiconsIcon icon={ArrowRight01Icon} size={16} strokeWidth={2} aria-hidden />
                    </Button>
                  }
                />
              ))}
            </div>
          )}
        </section>

        {data && data.packs.length > 0 && (
          <section className="mt-24">
            <div className="text-center">
              <h2 className="font-heading text-3xl font-semibold tracking-tight">Need more tokens? Top up.</h2>
              <p className="mt-2 text-sm text-muted-foreground">Top-up tokens never expire. Subscribers get a discount on every top-up.</p>
            </div>
            <div className="mx-auto mt-8 grid max-w-3xl gap-4 sm:grid-cols-2">
              {data.packs.map((pack) => (
                <div key={pack.key} className="flex items-center justify-between gap-4 rounded-2xl border border-border bg-card/70 p-6 backdrop-blur-[14px]">
                  <div>
                    <p className="font-heading text-2xl font-semibold">{pack.tokens.toLocaleString('en-US')} tokens</p>
                    <p className="text-sm text-muted-foreground">{pack.name}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-heading text-xl font-semibold">{usd(pack.price_usd, 0)}</p>
                    {pack.subscriber_discount_percent > 0 && (
                      <p className="text-xs text-emerald-300">
                        {usd(pack.subscriber_price_usd, 0)} for subscribers
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        <p className="mx-auto mt-16 max-w-2xl text-center text-xs text-muted-foreground">
          Plan tokens are added each month and unused ones expire at the end of the month. Top-up tokens do not expire. 1 token = $0.001 of usage.
        </p>
      </main>
    </div>
  )
}
