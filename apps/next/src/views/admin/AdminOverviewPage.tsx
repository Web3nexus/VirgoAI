import { useState } from 'react'
import { Link } from '@/lib/router'
import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon, ArrowUp01Icon } from '@hugeicons/core-free-icons'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminOverview } from '@/features/admin/hooks'
import type { AdminOverview, DashboardRange } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'
import { percent, usd } from '@/lib/money'
import { cn } from '@/lib/utils'

const RANGES: DashboardRange[] = [7, 30, 90]

/** Chart colours. Fixed hex values keep the charts readable in both themes. */
const COLOR = {
  revenue: '#8b7bff',
  cost: '#f59e0b',
  earned: '#34d399',
  failed: '#f87171',
  signups: '#22d3ee',
  grid: 'rgba(148,163,184,0.15)',
  axis: '#94a3b8',
}
const PIE_COLORS = ['#8b7bff', '#22d3ee', '#34d399', '#f59e0b', '#f472b6', '#94a3b8']
const STATUS_COLOR: Record<string, string> = { succeeded: '#34d399', failed: '#f87171', running: '#fbbf24' }

export function AdminOverviewPage() {
  const [range, setRange] = useState<DashboardRange>(30)
  const { data, isLoading, isError } = useAdminOverview(range)

  return (
    <div className="space-y-8">
      <PageHeader
        title="Dashboard"
        description="Revenue, provider cost, and what you kept. Compared with the same number of days before."
        action={<RangePicker value={range} onChange={setRange} />}
      />

      {isLoading && !data ? (
        <Skeleton className="h-[640px] rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">The dashboard could not be loaded.</p>
      ) : (
        <Dashboard data={data} />
      )}
    </div>
  )
}

function RangePicker({ value, onChange }: { value: DashboardRange; onChange: (range: DashboardRange) => void }) {
  return (
    <div role="radiogroup" aria-label="Date range" className="inline-flex rounded-lg border border-border p-0.5">
      {RANGES.map((item) => (
        <button
          key={item}
          type="button"
          role="radio"
          aria-checked={value === item}
          onClick={() => onChange(item)}
          className={cn(
            'rounded-md px-3 py-1 text-xs transition-colors',
            value === item ? 'bg-sidebar-accent text-foreground' : 'text-muted-foreground hover:text-foreground',
          )}
        >
          Last {item} days
        </button>
      ))}
    </div>
  )
}

function Dashboard({ data }: { data: AdminOverview }) {
  const k = data.kpis

  return (
    <div className="space-y-6">
      <section aria-label="Key figures" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiTile label="Paid by users" value={usd(k.revenue_usd)} current={k.revenue_usd} previous={k.revenue_prev_usd} accent={COLOR.revenue} />
        <KpiTile label="You kept" value={usd(k.earned_usd)} current={k.earned_usd} previous={k.earned_prev_usd} accent={COLOR.earned} hint={`${percent(data.kept_share_percent)} of each price`} />
        <KpiTile label="Provider cost" value={usd(k.cost_usd)} accent={COLOR.cost} hint="What fal and Anthropic billed" />
        <KpiTile
          label="Runs"
          value={String(k.runs)}
          current={k.runs}
          previous={k.runs_prev}
          accent={COLOR.signups}
          hint={k.success_rate === null ? 'No runs in this range' : `${k.success_rate}% finished`}
        />
      </section>

      <section aria-label="Revenue and cost" className="grid gap-6 xl:grid-cols-3">
        <Card className="glass border-border/60 xl:col-span-2">
          <CardContent className="p-5">
            <ChartHeader title="Revenue, cost, and what you kept" subtitle={`Daily, last ${data.range} days`} />
            <div className="mt-4 h-72">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data.series} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                  <defs>
                    <linearGradient id="gRevenue" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor={COLOR.revenue} stopOpacity={0.45} />
                      <stop offset="100%" stopColor={COLOR.revenue} stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="gEarned" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor={COLOR.earned} stopOpacity={0.35} />
                      <stop offset="100%" stopColor={COLOR.earned} stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid stroke={COLOR.grid} vertical={false} />
                  <XAxis dataKey="date" tickFormatter={shortDate} stroke={COLOR.axis} tickLine={false} axisLine={false} minTickGap={24} fontSize={12} />
                  <YAxis tickFormatter={(value) => `$${value}`} stroke={COLOR.axis} tickLine={false} axisLine={false} fontSize={12} width={56} />
                  <Tooltip content={<ChartTooltip />} />
                  <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                  <Area type="monotone" dataKey="revenue" name="Paid" stroke={COLOR.revenue} fill="url(#gRevenue)" strokeWidth={2} />
                  <Area type="monotone" dataKey="cost" name="Cost" stroke={COLOR.cost} fill="transparent" strokeWidth={2} strokeDasharray="4 4" />
                  <Area type="monotone" dataKey="earned" name="Kept" stroke={COLOR.earned} fill="url(#gEarned)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="p-5">
            <ChartHeader title="Run outcomes" subtitle={`Last ${data.range} days`} />
            {data.status.length === 0 ? (
              <EmptyChart />
            ) : (
              <div className="relative mt-4 h-72">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie data={data.status} dataKey="count" nameKey="status" innerRadius="62%" outerRadius="88%" paddingAngle={3} stroke="none">
                      {data.status.map((row) => (
                        <Cell key={row.status} fill={STATUS_COLOR[row.status] ?? COLOR.axis} />
                      ))}
                    </Pie>
                    <Tooltip content={<ChartTooltip />} />
                  </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                  <p className="font-heading text-3xl font-semibold">{k.success_rate === null ? '—' : `${k.success_rate}%`}</p>
                  <p className="text-xs text-muted-foreground">finished</p>
                </div>
              </div>
            )}
            <ul className="mt-2 space-y-1 text-sm">
              {data.status.map((row) => (
                <li key={row.status} className="flex items-center justify-between">
                  <span className="flex items-center gap-2 capitalize">
                    <span className="size-2.5 rounded-full" style={{ background: STATUS_COLOR[row.status] }} aria-hidden />
                    {row.status}
                  </span>
                  <span className="text-muted-foreground">{row.count}</span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      </section>

      <section aria-label="Activity" className="grid gap-6 xl:grid-cols-3">
        <Card className="glass border-border/60 xl:col-span-2">
          <CardContent className="p-5">
            <ChartHeader title="Runs per day" subtitle="Finished and failed" />
            <div className="mt-4 h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.series} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                  <CartesianGrid stroke={COLOR.grid} vertical={false} />
                  <XAxis dataKey="date" tickFormatter={shortDate} stroke={COLOR.axis} tickLine={false} axisLine={false} minTickGap={24} fontSize={12} />
                  <YAxis allowDecimals={false} stroke={COLOR.axis} tickLine={false} axisLine={false} fontSize={12} width={36} />
                  <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(148,163,184,0.08)' }} />
                  <Bar dataKey="runs" name="Runs" fill={COLOR.revenue} radius={[4, 4, 0, 0]} />
                  <Bar dataKey="failed" name="Failed" fill={COLOR.failed} radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="p-5">
            <ChartHeader title="Revenue by tool" subtitle={`Last ${data.range} days`} />
            {data.by_tool.length === 0 ? (
              <EmptyChart />
            ) : (
              <div className="mt-4 h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <PieChart>
                    <Pie data={data.by_tool} dataKey="revenue_usd" nameKey="tool" innerRadius="55%" outerRadius="85%" paddingAngle={2} stroke="none">
                      {data.by_tool.map((row, index) => (
                        <Cell key={row.tool} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip content={<ChartTooltip />} />
                    <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                  </PieChart>
                </ResponsiveContainer>
              </div>
            )}
          </CardContent>
        </Card>
      </section>

      <section aria-label="Models and sign-ups" className="grid gap-6 xl:grid-cols-3">
        <Card className="glass border-border/60">
          <CardContent className="p-5">
            <ChartHeader title="Top models" subtitle="Paid, by model" />
            {data.by_model.length === 0 ? (
              <EmptyChart />
            ) : (
              <div className="mt-4 h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.by_model} layout="vertical" margin={{ top: 0, right: 16, bottom: 0, left: 0 }}>
                    <CartesianGrid stroke={COLOR.grid} horizontal={false} />
                    <XAxis type="number" tickFormatter={(value) => `$${value}`} stroke={COLOR.axis} tickLine={false} axisLine={false} fontSize={12} />
                    <YAxis type="category" dataKey="model" width={150} stroke={COLOR.axis} tickLine={false} axisLine={false} fontSize={11} />
                    <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(148,163,184,0.08)' }} />
                    <Bar dataKey="revenue_usd" name="Paid" fill={COLOR.revenue} radius={[0, 4, 4, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="p-5">
            <ChartHeader title="New sign-ups" subtitle={`${k.signups} in the range`} delta={{ current: k.signups, previous: k.signups_prev }} />
            <div className="mt-4 h-64">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data.series} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                  <CartesianGrid stroke={COLOR.grid} vertical={false} />
                  <XAxis dataKey="date" tickFormatter={shortDate} stroke={COLOR.axis} tickLine={false} axisLine={false} minTickGap={24} fontSize={12} />
                  <YAxis allowDecimals={false} stroke={COLOR.axis} tickLine={false} axisLine={false} fontSize={12} width={36} />
                  <Tooltip content={<ChartTooltip />} />
                  <Area type="monotone" dataKey="signups" name="Sign-ups" stroke={COLOR.signups} fill={COLOR.signups} fillOpacity={0.15} strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="space-y-4 p-5">
            <ChartHeader title="Platform totals" subtitle="All time" />
            <dl className="grid grid-cols-2 gap-4 text-sm">
              <Total label="Paid" value={usd(data.revenue_usd)} />
              <Total label="Provider cost" value={usd(data.provider_cost_usd)} />
              <Total label="You kept" value={usd(data.earned_usd)} />
              <Total label="Credit held" value={usd(data.credit_held_usd)} hint="Owed back to users" />
              <Total label="Users" value={String(data.users.total)} hint={`${data.users.suspended} suspended`} />
              <Total label="Running now" value={String(data.runs.running)} hint={`${data.runs.failed} failed all time`} />
            </dl>
            <p className="text-xs text-muted-foreground">Margin {percent(data.margin_percent)}. You keep {percent(data.kept_share_percent)} of each price.</p>
          </CardContent>
        </Card>
      </section>

      <section aria-label="Recent runs" className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Recent runs</h2>
          <Link to="/securegate/users" className="text-xs text-muted-foreground hover:text-foreground">Users →</Link>
        </div>
        {data.recent_runs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No runs yet.</p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-border/60">
            <table className="w-full text-left text-sm">
              <thead className="bg-card/60 text-xs text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">When</th>
                  <th className="px-4 py-2 font-medium">User</th>
                  <th className="px-4 py-2 font-medium">Tool</th>
                  <th className="px-4 py-2 font-medium">Model</th>
                  <th className="px-4 py-2 font-medium">Status</th>
                  <th className="px-4 py-2 font-medium">Charged</th>
                </tr>
              </thead>
              <tbody>
                {data.recent_runs.map((run) => (
                  <tr key={run.id} className="border-t border-border/60">
                    <td className="px-4 py-2 text-muted-foreground">{run.created_at ? formatTime(run.created_at) : '—'}</td>
                    <td className="px-4 py-2">{run.user ?? '—'}</td>
                    <td className="px-4 py-2 capitalize">{run.tool}</td>
                    <td className="px-4 py-2 text-muted-foreground">{run.model_key ?? '—'}</td>
                    <td className="px-4 py-2">
                      <Badge variant={run.status === 'failed' ? 'destructive' : 'outline'} className="capitalize">{run.status}</Badge>
                    </td>
                    <td className="px-4 py-2">{usd(run.price_usd, 4)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  )
}

function KpiTile({
  label,
  value,
  hint,
  current,
  previous,
  accent,
}: {
  label: string
  value: string
  hint?: string
  current?: number
  previous?: number
  accent: string
}) {
  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-3 p-5">
        <div className="flex items-center justify-between">
          <p className="text-xs text-muted-foreground">{label}</p>
          <span className="size-2 rounded-full" style={{ background: accent }} aria-hidden />
        </div>
        <div className="flex items-end justify-between gap-3">
          <p className="font-heading text-2xl font-semibold tracking-tight">{value}</p>
          {current !== undefined && previous !== undefined && <DeltaChip current={current} previous={previous} />}
        </div>
        {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
      </CardContent>
    </Card>
  )
}

/** Change against the previous period, as a coloured chip. */
function DeltaChip({ current, previous }: { current: number; previous: number }) {
  if (previous === 0 && current === 0) return <span className="text-xs text-muted-foreground">No change</span>
  if (previous === 0) return <span className="rounded-full bg-emerald-400/10 px-2 py-0.5 text-xs text-emerald-300">New</span>

  const change = ((current - previous) / Math.abs(previous)) * 100
  const up = change >= 0

  return (
    <span className={cn('inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs', up ? 'bg-emerald-400/10 text-emerald-300' : 'bg-red-400/10 text-red-300')}>
      <HugeiconsIcon icon={up ? ArrowUp01Icon : ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      {Math.abs(change).toFixed(1)}%
    </span>
  )
}

function ChartHeader({
  title,
  subtitle,
  delta,
}: {
  title: string
  subtitle: string
  delta?: { current: number; previous: number }
}) {
  return (
    <div className="flex items-start justify-between gap-3">
      <div>
        <h2 className="font-heading text-sm font-semibold">{title}</h2>
        <p className="text-xs text-muted-foreground">{subtitle}</p>
      </div>
      {delta && <DeltaChip current={delta.current} previous={delta.previous} />}
    </div>
  )
}

function EmptyChart() {
  return <p className="flex h-64 items-center justify-center text-sm text-muted-foreground">No data in this range yet.</p>
}

function Total({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="font-heading text-lg font-semibold">{value}</dd>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  )
}

function shortDate(value: string): string {
  const date = new Date(`${value}T00:00:00Z`)
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' })
}

/** Tooltip body: each series as a labelled line, money formatted. */
function ChartTooltip({
  active,
  payload,
  label,
}: {
  active?: boolean
  payload?: { name?: string; value?: number | string; color?: string; dataKey?: string }[]
  label?: string
}) {
  if (!active || !payload?.length) return null

  return (
    <div className="rounded-lg border border-border bg-background/95 px-3 py-2 text-xs shadow-lg">
      {label && <p className="mb-1 font-medium">{shortDate(String(label))}</p>}
      {payload.map((item) => {
        const isMoney = item.dataKey === 'revenue' || item.dataKey === 'cost' || item.dataKey === 'earned' || item.dataKey === 'revenue_usd'
        const value = Number(item.value ?? 0)
        return (
          <p key={item.dataKey ?? item.name} className="flex items-center gap-2">
            <span className="size-2 rounded-full" style={{ background: item.color }} aria-hidden />
            <span className="text-muted-foreground">{item.name}</span>
            <span className="ml-auto font-medium">{isMoney ? usd(value) : value}</span>
          </p>
        )
      })}
    </div>
  )
}
