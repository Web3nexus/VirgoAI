import { cn } from '@/lib/utils'
import type { Quote } from '@/features/usage/api'

/** The price of an action, shown beside its button before the user presses it. */
export function CostBadge({ quote, loading, className }: { quote: Quote | undefined; loading?: boolean; className?: string }) {
  if (loading && !quote) return <span className={cn('text-xs text-muted-foreground', className)}>Pricing…</span>
  if (!quote) return null

  if (quote.price_usd === null) {
    return <span className={cn('text-xs text-amber-300', className)}>{quote.message ?? 'Not priced yet'}</span>
  }

  if (quote.price_usd === 0) {
    return <span className={cn('text-xs text-emerald-300', className)}>{quote.label ?? 'Free'}</span>
  }

  const tokens = quote.tokens ?? 0
  return (
    <span className={cn('text-xs', quote.affordable ? 'text-muted-foreground' : 'text-red-300', className)}>
      {quote.estimate ? '≈ ' : ''}
      {tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })} tokens
      {quote.affordable ? '' : ' · not enough tokens'}
    </span>
  )
}
