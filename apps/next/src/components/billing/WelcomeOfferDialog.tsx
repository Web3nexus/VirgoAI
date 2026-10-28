import { useEffect, useState } from 'react'
import { Link } from '@/lib/router'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useBilling, usePublicPricing } from '@/features/billing/hooks'
import type { PersonalPromo } from '@/features/billing/types'
import { usd } from '@/lib/money'

const TWO = (value: number) => String(value).padStart(2, '0')

/** Whole dollars, or cents when the price has them, so $11.50 never reads as $12. */
const price = (value: number) => usd(value, value % 1 === 0 ? 0 : 2)

/** Time left as hh:mm:ss. Never negative. */
function countdown(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000))
  return `${TWO(Math.floor(total / 3600))}:${TWO(Math.floor((total % 3600) / 60))}:${TWO(total % 60)}`
}

/** Remembered for this browser session only, so a closed offer stays closed while the user moves around. */
function dismissKey(promo: PersonalPromo): string {
  return `welcome-offer:${promo.code}:${promo.ends_at}`
}

function wasDismissed(promo: PersonalPromo): boolean {
  try {
    return window.sessionStorage.getItem(dismissKey(promo)) === '1'
  } catch {
    return false
  }
}

function rememberDismissed(promo: PersonalPromo) {
  try {
    window.sessionStorage.setItem(dismissKey(promo), '1')
  } catch {
    // Storage can be blocked. The offer then shows again on the next page, which is harmless.
  }
}

/**
 * The personal discount, shown once to a new account that has not bought a plan. The server decides who
 * sees it and when the window closes; this only shows the offer and counts down to that time.
 */
export function WelcomeOfferDialog() {
  const { data: billing } = useBilling()
  const { data: pricing } = usePublicPricing()
  const promo = billing?.promo ?? null
  const [open, setOpen] = useState(false)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (promo) setOpen(!wasDismissed(promo))
  }, [promo?.code, promo?.ends_at])

  useEffect(() => {
    if (!promo) return
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [promo])

  if (!promo || !pricing) return null

  const remaining = new Date(promo.ends_at).getTime() - now
  if (remaining <= 0) return null

  function close(next: boolean) {
    setOpen(next)
    if (!next && promo) rememberDismissed(promo)
  }

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="font-heading text-2xl">You got a personal {promo.percent}% discount</DialogTitle>
          <DialogDescription>Your welcome offer on every plan. It ends when the timer runs out.</DialogDescription>
        </DialogHeader>

        <div className="flex items-center justify-between rounded-xl border border-border/60 bg-background/40 px-4 py-3">
          <div>
            <p className="text-xs text-muted-foreground">Code applied</p>
            <p className="font-mono text-sm font-medium">{promo.code}</p>
          </div>
          <div className="text-right">
            <p className="text-xs text-muted-foreground">Time left</p>
            <p className="font-mono text-xl font-semibold tabular-nums">{countdown(remaining)}</p>
          </div>
        </div>

        <ul className="divide-y divide-border/60 rounded-xl border border-border/60">
          {pricing.plans.map((plan) => {
            const offer = promo.plans[plan.key]
            if (!offer) return null
            return (
              <li key={plan.key} className="flex items-center justify-between gap-4 px-4 py-3">
                <div>
                  <p className="font-medium">{plan.name}</p>
                  <p className="text-xs text-muted-foreground">{plan.monthly_tokens.toLocaleString('en-US')} tokens a month</p>
                </div>
                <div className="flex items-baseline gap-2">
                  <span className="text-sm text-muted-foreground line-through">{price(plan.monthly_usd)}</span>
                  <span className="font-heading text-xl font-semibold">{price(offer.monthly_usd)}</span>
                  <span className="text-xs text-muted-foreground">/ mo</span>
                </div>
              </li>
            )
          })}
        </ul>

        <Button className="w-full" nativeButton={false} render={<Link to="/app/billing" onClick={() => rememberDismissed(promo)} />}>
          Claim your discount
        </Button>
      </DialogContent>
    </Dialog>
  )
}
