import { useState, type FormEvent } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Stat, inputClass } from '@/components/admin/AdminLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminPricing, useSyncPricing, useUpdateMargin, useUpdatePrice } from '@/features/admin/hooks'
import type { ModelPriceRow } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'
import { percent, usdUnit } from '@/lib/money'

const UNIT_LABEL: Record<string, string> = {
  image: 'per image',
  video: 'per video',
  second: 'per second',
  input_mtok: 'per 1M input tokens',
  output_mtok: 'per 1M output tokens',
}

const SOURCE_LABEL: Record<string, string> = {
  fal_api: 'fal pricing API',
  anthropic_docs: 'Anthropic pricing page',
  admin: 'Set by you',
}

export function AdminPricingPage() {
  const { data, isLoading, isError } = useAdminPricing()
  const sync = useSyncPricing()

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !data) return <p className="text-sm text-red-300">Prices could not be loaded.</p>

  const lastSynced = data.prices
    .filter((price) => price.source !== 'admin' && price.verified_at)
    .map((price) => price.verified_at as string)
    .sort()
    .at(-1)

  return (
    <div className="space-y-8">
      <PageHeader
        title="Pricing"
        description="What the providers cost, and what users pay. The price a user pays is the provider cost plus your margin."
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <MarginForm current={data.margin_percent} keptShare={data.kept_share_percent} />
        <Card className="glass border-border/60">
          <CardContent className="space-y-4 p-5">
            <div>
              <h2 className="font-heading text-base font-semibold">Refresh provider prices</h2>
              <p className="text-xs text-muted-foreground">
                Reads fal&apos;s pricing API with your fal key, and reapplies Anthropic&apos;s published prices. Prices you set by hand are kept.
              </p>
            </div>
            <Stat label="Last refreshed" value={lastSynced ? formatTime(lastSynced) : 'Never'} />
            <Button variant="outline" onClick={() => sync.mutate()} disabled={sync.isPending}>
              {sync.isPending ? 'Refreshing…' : 'Refresh prices'}
            </Button>
            {sync.data && sync.data.errors.length > 0 && (
              <ul className="space-y-1 text-sm text-amber-300">
                {sync.data.errors.map((error) => (
                  <li key={error}>{error}</li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <section aria-label="Prices" className="space-y-3">
        <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Provider prices</h2>
        {data.prices.length === 0 ? (
          <p className="text-sm text-muted-foreground">No prices yet. Refresh prices to read them from the providers.</p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-border/60">
            <table className="w-full text-left text-sm">
              <thead className="bg-card/60 text-xs text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">Model</th>
                  <th className="px-4 py-2 font-medium">Billed</th>
                  <th className="px-4 py-2 font-medium">Provider cost</th>
                  <th className="px-4 py-2 font-medium">User pays</th>
                  <th className="px-4 py-2 font-medium">Source</th>
                </tr>
              </thead>
              <tbody>
                {data.prices.map((price) => (
                  <PriceRow key={price.id} price={price} />
                ))}
              </tbody>
            </table>
          </div>
        )}
        <p className="text-xs text-muted-foreground">
          Models without a price cannot run for users. A model appears here once its price is read or set.
        </p>
      </section>
    </div>
  )
}

function MarginForm({ current, keptShare }: { current: number; keptShare: number }) {
  const [value, setValue] = useState(String(current))
  const update = useUpdateMargin()
  const parsed = Number(value)
  const valid = Number.isInteger(parsed) && parsed >= 0 && parsed <= 500

  function submit(event: FormEvent) {
    event.preventDefault()
    update.mutate(parsed)
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div>
          <h2 className="font-heading text-base font-semibold">Your margin</h2>
          <p className="text-xs text-muted-foreground">
            A {percent(current)} margin means you keep {percent(keptShare)} of every price. Users always see the full price.
          </p>
        </div>
        <form onSubmit={submit} className="flex items-end gap-3">
          <div className="space-y-2">
            <Label htmlFor="margin-percent">Margin (%)</Label>
            <Input id="margin-percent" type="number" min={0} max={500} step={1} value={value} onChange={(e) => setValue(e.target.value)} />
          </div>
          <Button type="submit" disabled={!valid || parsed === current || update.isPending}>
            Save margin
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function PriceRow({ price }: { price: ModelPriceRow }) {
  const [editing, setEditing] = useState(false)
  const [value, setValue] = useState(String(price.cost_usd))
  const update = useUpdatePrice()

  function save() {
    update.mutate({ id: price.id, costUsd: Number(value) }, { onSuccess: () => setEditing(false) })
  }

  return (
    <tr className="border-t border-border/60">
      <td className="px-4 py-2">
        <p className="font-medium">{price.model_key}</p>
        <p className="text-xs text-muted-foreground">{price.provider_key}</p>
      </td>
      <td className="px-4 py-2 text-muted-foreground">{UNIT_LABEL[price.unit] ?? price.unit}</td>
      <td className="px-4 py-2">
        {editing ? (
          <div className="flex items-center gap-2">
            <label className="sr-only" htmlFor={`cost-${price.id}`}>Provider cost</label>
            <input id={`cost-${price.id}`} className={`${inputClass} w-28`} type="number" step="0.000001" min={0} value={value} onChange={(e) => setValue(e.target.value)} />
            <Button size="sm" onClick={save} disabled={update.isPending}>Save</Button>
            <Button size="sm" variant="ghost" onClick={() => setEditing(false)}>Cancel</Button>
          </div>
        ) : (
          <button type="button" className="hover:text-brand" onClick={() => setEditing(true)}>
            {usdUnit(price.cost_usd)}
          </button>
        )}
      </td>
      <td className="px-4 py-2">{usdUnit(price.sell_usd)}</td>
      <td className="px-4 py-2 text-muted-foreground">{SOURCE_LABEL[price.source] ?? price.source}</td>
    </tr>
  )
}
