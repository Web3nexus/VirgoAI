import { useState, type FormEvent } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { Add01Icon, CheckmarkCircle02Icon, Cancel01Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton, Stat } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import {
  useAdminPricing,
  useBillingAdmin,
  useCreateFlutterwavePlans,
  useCancelSubscription,
  useDeletePack,
  useDeletePlan,
  useMarkPurchaseFailed,
  useMarkPurchasePaid,
  usePurchases,
  useSavePack,
  useSavePlan,
  useSubscriptions,
} from '@/features/admin/hooks'
import type { PackRow, PlanEconomics, PlanRow } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'
import { percent, usd } from '@/lib/money'
import { cn } from '@/lib/utils'

type Editing = { kind: 'plan'; row: PlanRow | null } | { kind: 'pack'; row: PackRow | null } | null

export function AdminBillingPage() {
  const { data, isLoading, isError } = useBillingAdmin()
  const [editing, setEditing] = useState<Editing>(null)

  if (isLoading) return <Skeleton className="h-[480px] rounded-xl" />
  if (isError || !data) return <p className="text-sm text-red-300">Billing could not be loaded.</p>

  const activeSubs = data.subscriptions.active
  const keptPerMonth = data.economics.filter((plan) => plan.active).reduce((sum, plan) => sum + plan.monthly.kept_usd, 0)

  return (
    <div className="space-y-10">
      <PageHeader title="Billing and plans" description="Three plans, monthly and yearly, plus top-ups. Each plan's margin is worked out from your pricing." />

      <section aria-label="Subscriptions at a glance" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Active plans" value={String(activeSubs)} hint={`${data.subscriptions.past_due} need renewal`} />
        <Stat label="You keep per month" value={usd(keptPerMonth)} hint="If every active plan's credit is fully used" />
        <Stat label="Waiting for payment" value={String(data.pending_purchases)} hint="Confirm them below" />
        <Stat label="Ended plans" value={String(data.subscriptions.ended)} />
      </section>

      <section aria-label="Plans" className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="font-heading text-lg font-semibold">Plans</h2>
          <Button className="gap-2" onClick={() => setEditing({ kind: 'plan', row: null })}>
            <HugeiconsIcon icon={Add01Icon} size={16} strokeWidth={1.75} aria-hidden />
            New plan
          </Button>
        </div>
        <div className="overflow-x-auto rounded-xl border border-border/60">
          <table className="w-full text-left text-sm">
            <thead className="bg-card/60 text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Plan</th>
                <th className="px-4 py-2 font-medium">Price</th>
                <th className="px-4 py-2 font-medium">Monthly credit</th>
                <th className="px-4 py-2 font-medium">Provider budget / mo</th>
                <th className="px-4 py-2 font-medium">You keep / mo</th>
                <th className="px-4 py-2 font-medium">Subscribers</th>
                <th className="px-4 py-2 text-right font-medium"><span className="sr-only">Edit</span></th>
              </tr>
            </thead>
            <tbody>
              {data.economics.map((plan) => {
                const row = data.plans.find((item) => item.key === plan.key) ?? null
                return <PlanTableRow key={plan.key} plan={plan} row={row} onEdit={() => setEditing({ kind: 'plan', row })} />
              })}
            </tbody>
          </table>
        </div>
        <p className="text-xs text-muted-foreground">
          The provider budget is the part of each plan's credit that pays the providers. The rest is yours. The
          figures assume the whole monthly credit is used, which is the worst case for you.
        </p>
      </section>

      {editing?.kind === 'plan' && (
        <PlanEditor key={editing.row?.key ?? 'new'} row={editing.row} onDone={() => setEditing(null)} />
      )}

      <section aria-label="Top-ups" className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="font-heading text-lg font-semibold">Top-up packs</h2>
          <Button variant="outline" className="gap-2" onClick={() => setEditing({ kind: 'pack', row: null })}>
            <HugeiconsIcon icon={Add01Icon} size={16} strokeWidth={1.75} aria-hidden />
            New top-up
          </Button>
        </div>
        <div className="overflow-x-auto rounded-xl border border-border/60">
          <table className="w-full text-left text-sm">
            <thead className="bg-card/60 text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Pack</th>
                <th className="px-4 py-2 font-medium">Credit</th>
                <th className="px-4 py-2 font-medium">Price</th>
                <th className="px-4 py-2 font-medium">Subscriber price</th>
                <th className="px-4 py-2 font-medium">Active</th>
                <th className="px-4 py-2 text-right font-medium"><span className="sr-only">Edit</span></th>
              </tr>
            </thead>
            <tbody>
              {data.packs.map((pack) => (
                <tr key={pack.id} className="border-t border-border/60">
                  <td className="px-4 py-2 font-medium">{pack.name}</td>
                  <td className="px-4 py-2">{usd(pack.credits_usd, 0)}</td>
                  <td className="px-4 py-2">{usd(pack.price_usd, 0)}</td>
                  <td className="px-4 py-2">{usd(pack.subscriber_price_usd, 0)} <span className="text-xs text-muted-foreground">(−{pack.subscriber_discount_percent}%)</span></td>
                  <td className="px-4 py-2">{pack.active ? 'Yes' : 'Hidden'}</td>
                  <td className="px-4 py-2 text-right">
                    <Button variant="outline" size="sm" onClick={() => setEditing({ kind: 'pack', row: pack })}>Edit</Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {editing?.kind === 'pack' && <PackEditor key={editing.row?.key ?? 'new-pack'} row={editing.row} onDone={() => setEditing(null)} />}

      <PurchasesPanel />
      <SubscriptionsPanel />
    </div>
  )
}

function PlanTableRow({ plan, row, onEdit }: { plan: PlanEconomics; row: PlanRow | null; onEdit: () => void }) {
  const createFlutterwave = useCreateFlutterwavePlans()
  const yearlyPerMonth = plan.yearly_price_usd / 12
  const savings = Math.round((1 - yearlyPerMonth / plan.monthly_price_usd) * 100)

  return (
    <tr className="border-t border-border/60">
      <td className="px-4 py-2">
        <p className="font-medium">{plan.name}</p>
        <p className="text-xs text-muted-foreground">{plan.key}{!plan.active && ' · hidden'}</p>
      </td>
      <td className="px-4 py-2">
        {usd(plan.monthly_price_usd, 0)}/mo <span className="text-xs text-muted-foreground">· {usd(plan.yearly_price_usd, 0)}/yr ({savings}% off)</span>
      </td>
      <td className="px-4 py-2">{usd(plan.monthly_credit_usd, 0)}</td>
      <td className="px-4 py-2">{usd(plan.monthly.provider_budget_usd)}</td>
      <td className="px-4 py-2 font-medium text-emerald-300">{usd(plan.monthly.kept_usd)} <span className="text-xs text-muted-foreground">({percent(Math.round(plan.monthly.kept_usd / plan.monthly_price_usd * 100))})</span></td>
      <td className="px-4 py-2">{plan.subscribers}</td>
      <td className="px-4 py-2 text-right">
        <span className="inline-flex gap-2">
          {row && (row.flutterwave_monthly_plan_id === null || row.flutterwave_yearly_plan_id === null) && (
            <Button variant="ghost" size="sm" disabled={createFlutterwave.isPending} onClick={() => createFlutterwave.mutate(row.id)}>
              Set up on Flutterwave
            </Button>
          )}
          <Button variant="outline" size="sm" onClick={onEdit}>Edit</Button>
        </span>
      </td>
    </tr>
  )
}

function PlanEditor({ row, onDone }: { row: PlanRow | null; onDone: () => void }) {
  const [key, setKey] = useState(row?.key ?? '')
  const [name, setName] = useState(row?.name ?? '')
  const [tagline, setTagline] = useState(row?.tagline ?? '')
  const [monthly, setMonthly] = useState(String(row?.monthly_usd ?? 19))
  const [yearly, setYearly] = useState(String(row?.yearly_usd ?? 190))
  const [credit, setCredit] = useState(String(row?.monthly_credit_usd ?? 19))
  const [features, setFeatures] = useState((row?.features ?? []).join('\n'))
  const [highlighted, setHighlighted] = useState(row?.highlighted ?? false)
  const [active, setActive] = useState(row?.active ?? true)
  const [sort, setSort] = useState(String(row?.sort ?? 1))
  const save = useSavePlan()
  const remove = useDeletePlan()
  const [paddleMonthly, setPaddleMonthly] = useState(row?.paddle_monthly_price_id ?? '')
  const [paddleYearly, setPaddleYearly] = useState(row?.paddle_yearly_price_id ?? '')

  const m = Number(monthly) || 0
  const y = Number(yearly) || 0
  const c = Number(credit) || 0
  const yearlyPerMonth = y / 12
  const savings = m > 0 ? Math.round((1 - yearlyPerMonth / m) * 100) : 0
  const { data: pricing } = useAdminPricing()
  const margin = pricing?.margin_percent ?? 0
  const budget = c / (1 + margin / 100)

  function submit(event: FormEvent) {
    event.preventDefault()
    const payload = {
      ...(row ? {} : { key }),
      name,
      tagline: tagline || null,
      monthly_usd: m,
      yearly_usd: y,
      monthly_credit_usd: c,
      features: features.split('\n').map((line) => line.trim()).filter(Boolean),
      highlighted,
      active,
      sort: Number(sort),
      paddle_monthly_price_id: paddleMonthly || null,
      paddle_yearly_price_id: paddleYearly || null,
    }
    save.mutate({ id: row && row.id ? row.id : null, payload }, { onSuccess: onDone })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="p-6">
        <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
          <div className="space-y-4">
            <h3 className="font-heading text-base font-semibold">{row ? `Edit ${row.name}` : 'New plan'}</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="plan-key">Key</Label>
                <Input id="plan-key" value={key} onChange={(e) => setKey(e.target.value)} disabled={Boolean(row)} placeholder="team" required pattern="[a-z_]+" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="plan-name">Name</Label>
                <Input id="plan-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={64} />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="plan-tagline">Tagline</Label>
              <Input id="plan-tagline" value={tagline} onChange={(e) => setTagline(e.target.value)} maxLength={160} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <div className="space-y-2">
                <Label htmlFor="plan-monthly">Monthly price (USD)</Label>
                <Input id="plan-monthly" type="number" step="0.01" min={1} value={monthly} onChange={(e) => setMonthly(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="plan-yearly">Yearly price (USD)</Label>
                <Input id="plan-yearly" type="number" step="0.01" min={1} value={yearly} onChange={(e) => setYearly(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="plan-credit">Monthly credit (USD)</Label>
                <Input id="plan-credit" type="number" step="0.01" min={1} value={credit} onChange={(e) => setCredit(e.target.value)} required />
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="plan-paddle-monthly">Paddle price id (monthly)</Label>
                <Input id="plan-paddle-monthly" value={paddleMonthly} onChange={(e) => setPaddleMonthly(e.target.value)} placeholder="pri_…" pattern="pri_[a-z0-9]+" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="plan-paddle-yearly">Paddle price id (yearly)</Label>
                <Input id="plan-paddle-yearly" value={paddleYearly} onChange={(e) => setPaddleYearly(e.target.value)} placeholder="pri_…" pattern="pri_[a-z0-9]+" />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">Flutterwave plans are created with the button in the table. Paddle prices are created in your Paddle dashboard and pasted here.</p>
            <div className="space-y-2">
              <Label htmlFor="plan-features">Features (one per line)</Label>
              <Textarea id="plan-features" rows={4} value={features} onChange={(e) => setFeatures(e.target.value)} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-brand" checked={highlighted} onChange={(e) => setHighlighted(e.target.checked)} /> Highlight
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 accent-brand" checked={active} onChange={(e) => setActive(e.target.checked)} /> Show publicly
              </label>
              <div className="space-y-2">
                <Label htmlFor="plan-sort">Order</Label>
                <Input id="plan-sort" type="number" min={0} max={100} value={sort} onChange={(e) => setSort(e.target.value)} />
              </div>
            </div>
          </div>

          <div className="space-y-4 rounded-xl border border-border/60 bg-background/40 p-5">
            <p className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">What this plan means for you</p>
            <dl className="space-y-3 text-sm">
              <Row label="Per month, paid" value={usd(m)} />
              <Row label="Yearly, per month" value={`${usd(yearlyPerMonth)} (${savings}% off)`} />
              <Row label="User gets per month" value={usd(c)} />
              <Row label="Provider budget per month" value={usd(budget)} hint={`At a ${percent(margin)} margin`} />
              <Row label="You keep per month" value={usd(c - budget)} hint="If the whole credit is used" />
            </dl>
            <p className="text-xs text-muted-foreground">
              The yearly price should always be below twelve monthly payments. Otherwise the yearly plan costs the customer more.
            </p>
            <div className="flex flex-wrap gap-2 pt-2">
              <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Saving…' : 'Save plan'}</Button>
              <Button type="button" variant="ghost" onClick={onDone}>Close</Button>
              {row && row.id > 0 && (
                <span className="ml-auto">
                  <ConfirmButton label="Delete plan" confirmLabel="Confirm delete" disabled={remove.isPending} onConfirm={() => remove.mutate(row.id, { onSuccess: onDone })} />
                </span>
              )}
            </div>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function PackEditor({ row, onDone }: { row: PackRow | null; onDone: () => void }) {
  const [key, setKey] = useState(row?.key ?? '')
  const [name, setName] = useState(row?.name ?? '')
  const [credits, setCredits] = useState(String(row?.credits_usd ?? 25))
  const [price, setPrice] = useState(String(row?.price_usd ?? 25))
  const [discount, setDiscount] = useState(String(row?.subscriber_discount_percent ?? 10))
  const [active, setActive] = useState(row?.active ?? true)
  const [paddlePrice, setPaddlePrice] = useState(row?.paddle_price_id ?? '')
  const [paddleSubscriberPrice, setPaddleSubscriberPrice] = useState(row?.paddle_subscriber_price_id ?? '')
  const save = useSavePack()
  const remove = useDeletePack()

  function submit(event: FormEvent) {
    event.preventDefault()
    const payload = {
      ...(row ? {} : { key }),
      name,
      credits_usd: Number(credits),
      price_usd: Number(price),
      subscriber_discount_percent: Number(discount),
      active,
      sort: row?.sort ?? 9,
      paddle_price_id: paddlePrice || null,
      paddle_subscriber_price_id: paddleSubscriberPrice || null,
    }
    save.mutate({ id: row ? row.id : null, payload }, { onSuccess: onDone })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="p-6">
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-3">
          <h3 className="font-heading text-base font-semibold md:col-span-3">{row ? `Edit ${row.name}` : 'New top-up'}</h3>
          <div className="space-y-2">
            <Label htmlFor="pack-key">Key</Label>
            <Input id="pack-key" value={key} onChange={(e) => setKey(e.target.value)} disabled={Boolean(row)} placeholder="topup_50" required pattern="[a-z_0-9]+" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pack-name">Name</Label>
            <Input id="pack-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={64} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pack-discount">Subscriber discount (%)</Label>
            <Input id="pack-discount" type="number" min={0} max={90} value={discount} onChange={(e) => setDiscount(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pack-credit">Credit received (USD)</Label>
            <Input id="pack-credit" type="number" min={1} step="0.01" value={credits} onChange={(e) => setCredits(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pack-price">List price (USD)</Label>
            <Input id="pack-price" type="number" min={1} step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} required />
          </div>
          <label className="flex items-center gap-2 self-end text-sm">
            <input type="checkbox" className="size-4 accent-brand" checked={active} onChange={(e) => setActive(e.target.checked)} /> Show publicly
          </label>
          <div className="space-y-2">
            <Label htmlFor="pack-paddle">Paddle price id</Label>
            <Input id="pack-paddle" value={paddlePrice} onChange={(e) => setPaddlePrice(e.target.value)} placeholder="pri_…" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="pack-paddle-sub">Paddle price id (subscribers)</Label>
            <Input id="pack-paddle-sub" value={paddleSubscriberPrice} onChange={(e) => setPaddleSubscriberPrice(e.target.value)} placeholder="pri_… (optional)" />
          </div>
          <div className="flex flex-wrap items-center gap-2 md:col-span-3">
            <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Saving…' : 'Save top-up'}</Button>
            <Button type="button" variant="ghost" onClick={onDone}>Close</Button>
            {row && (
              <span className="ml-auto">
                <ConfirmButton label="Delete top-up" confirmLabel="Confirm delete" disabled={remove.isPending} onConfirm={() => remove.mutate(row.id, { onSuccess: onDone })} />
              </span>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function Row({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="flex items-baseline justify-between gap-4">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="text-right font-medium">
        {value}
        {hint && <span className="block text-[11px] font-normal text-muted-foreground">{hint}</span>}
      </dd>
    </div>
  )
}

function PurchasesPanel() {
  const [status, setStatus] = useState('pending')
  const { data, isLoading } = usePurchases(status)
  const paid = useMarkPurchasePaid()
  const failed = useMarkPurchaseFailed()

  return (
    <section aria-label="Purchases" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-semibold">Purchases</h2>
        <div role="tablist" aria-label="Purchase status" className="flex rounded-lg border border-border p-0.5">
          {['pending', 'paid', 'failed', ''].map((item) => (
            <button
              key={item || 'all'}
              type="button"
              role="tab"
              aria-selected={status === item}
              onClick={() => setStatus(item)}
              className={cn('rounded-md px-3 py-1 text-xs capitalize', status === item ? 'bg-sidebar-accent text-foreground' : 'text-muted-foreground')}
            >
              {item || 'All'}
            </button>
          ))}
        </div>
      </div>
      <p className="text-xs text-muted-foreground">
        Until a payment provider is connected, confirm a payment here after you receive it. Marking it paid grants the credit once.
      </p>
      {isLoading || !data ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">No purchases here.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/60">
          <table className="w-full text-left text-sm">
            <thead className="bg-card/60 text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">When</th>
                <th className="px-4 py-2 font-medium">User</th>
                <th className="px-4 py-2 font-medium">Bought</th>
                <th className="px-4 py-2 font-medium">Pays</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 text-right font-medium"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((purchase) => (
                <tr key={purchase.id} className="border-t border-border/60">
                  <td className="px-4 py-2 text-muted-foreground">{purchase.created_at ? formatTime(purchase.created_at) : '—'}</td>
                  <td className="px-4 py-2">{purchase.user ?? '—'}</td>
                  <td className="px-4 py-2 capitalize">{purchase.kind.replace('_', ' ')} · {purchase.item_key}{purchase.interval ? ` · ${purchase.interval}` : ''}</td>
                  <td className="px-4 py-2">{usd(purchase.amount_usd)} <span className="text-xs text-muted-foreground">for {usd(purchase.credits_usd)} credit</span></td>
                  <td className="px-4 py-2"><Badge variant={purchase.status === 'failed' ? 'destructive' : 'outline'} className="capitalize">{purchase.status}</Badge></td>
                  <td className="px-4 py-2 text-right">
                    {purchase.status === 'pending' && (
                      <span className="inline-flex gap-2">
                        <Button size="sm" className="gap-1" disabled={paid.isPending} onClick={() => paid.mutate(purchase.id)}>
                          <HugeiconsIcon icon={CheckmarkCircle02Icon} size={14} strokeWidth={2} aria-hidden /> Mark paid
                        </Button>
                        <Button size="sm" variant="ghost" className="gap-1" disabled={failed.isPending} onClick={() => failed.mutate(purchase.id)}>
                          <HugeiconsIcon icon={Cancel01Icon} size={14} strokeWidth={2} aria-hidden /> Failed
                        </Button>
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

function SubscriptionsPanel() {
  const { data, isLoading } = useSubscriptions()
  const cancel = useCancelSubscription()

  return (
    <section aria-label="Subscriptions" className="space-y-4">
      <h2 className="font-heading text-lg font-semibold">Subscriptions</h2>
      {isLoading || !data ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">No subscriptions yet.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/60">
          <table className="w-full text-left text-sm">
            <thead className="bg-card/60 text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">User</th>
                <th className="px-4 py-2 font-medium">Plan</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Period ends</th>
                <th className="px-4 py-2 text-right font-medium"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((sub) => (
                <tr key={sub.id} className="border-t border-border/60">
                  <td className="px-4 py-2">{sub.user ?? '—'}</td>
                  <td className="px-4 py-2 capitalize">{sub.plan_key} · {sub.interval}</td>
                  <td className="px-4 py-2">
                    <Badge variant={sub.status === 'active' ? 'default' : sub.status === 'past_due' ? 'destructive' : 'outline'}>
                      {sub.cancel_at_period_end ? 'Ending' : sub.status.replace('_', ' ')}
                    </Badge>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{formatTime(sub.current_period_end)}</td>
                  <td className="px-4 py-2 text-right">
                    {sub.status === 'active' && !sub.cancel_at_period_end && (
                      <ConfirmButton label="Cancel at period end" confirmLabel="Confirm" disabled={cancel.isPending} onConfirm={() => cancel.mutate(sub.id)} />
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

