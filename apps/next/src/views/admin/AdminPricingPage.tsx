import { useState, type FormEvent } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Stat, inputClass } from '@/components/admin/AdminLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminPricing, useSyncPricing, useUpdateMargin, useUpdatePrice, useUpdatePromo } from '@/features/admin/hooks'
import type { ModelPriceRow, PersonalPromoConfig } from '@/features/admin/types'
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
        <PromoForm current={data.promo} />
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

/** The welcome offer. Shown to accounts that have not bought yet, inside the window after they sign up. */
function PromoForm({ current }: { current: PersonalPromoConfig }) {
  const [draft, setDraft] = useState<PersonalPromoConfig>(current)
  const update = useUpdatePromo()
  const valid =
    /^[A-Za-z0-9_]{1,32}$/.test(draft.code) &&
    Number.isInteger(draft.percent) && draft.percent >= 1 && draft.percent <= 90 &&
    Number.isInteger(draft.window_hours) && draft.window_hours >= 1 && draft.window_hours <= 168
  const changed = JSON.stringify(draft) !== JSON.stringify(current)

  function submit(event: FormEvent) {
    event.preventDefault()
    update.mutate(draft, { onSuccess: (saved) => setDraft(saved) })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="font-heading text-base font-semibold">Welcome offer</h2>
            <p className="text-xs text-muted-foreground">
              A personal discount on every plan for new accounts that have not bought yet. The countdown starts at sign-up.
            </p>
          </div>
          <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs ${current.enabled ? 'bg-emerald-400/20 text-emerald-600 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}>
            {current.enabled ? 'Active' : 'Inactive'}
          </span>
        </div>
        <form onSubmit={submit} className="space-y-4">
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={draft.enabled} onChange={(e) => setDraft({ ...draft, enabled: e.target.checked })} />
            Show the offer to new accounts
          </label>
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="promo-code">Code</Label>
              <Input id="promo-code" value={draft.code} onChange={(e) => setDraft({ ...draft, code: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="promo-percent">Discount (%)</Label>
              <Input id="promo-percent" type="number" min={1} max={90} step={1} value={draft.percent} onChange={(e) => setDraft({ ...draft, percent: Number(e.target.value) })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="promo-window">Window (hours)</Label>
              <Input id="promo-window" type="number" min={1} max={168} step={1} value={draft.window_hours} onChange={(e) => setDraft({ ...draft, window_hours: Number(e.target.value) })} />
            </div>
          </div>
          <Button type="submit" disabled={!valid || !changed || update.isPending}>
            Save offer
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
