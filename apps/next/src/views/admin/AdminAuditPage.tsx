import { useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminAudit } from '@/features/admin/hooks'
import type { AuditRow } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'

const ACTION_LABEL: Record<string, string> = {
  'admin.login': 'Signed in',
  'admin.created': 'Super admin created',
  'admin.promoted': 'Promoted to super admin',
  'user.updated': 'Account changed',
  'user.credit': 'Credit changed',
  'pricing.margin': 'Margin changed',
  'pricing.price': 'Price set by hand',
  'pricing.sync': 'Prices refreshed',
}

export function AdminAuditPage() {
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAdminAudit(page)

  return (
    <div className="space-y-6">
      <PageHeader title="Audit log" description="Every change made in the owner console, newest first." />

      {isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">The audit log could not be loaded.</p>
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">Nothing has been changed yet.</p>
      ) : (
        <ul className="divide-y divide-border/60 rounded-xl border border-border/60">
          {data.data.map((row) => (
            <AuditItem key={row.id} row={row} />
          ))}
        </ul>
      )}

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2 text-sm">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Newer</Button>
          <span className="text-muted-foreground">Page {data.meta.current_page} of {data.meta.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>Older</Button>
        </div>
      )}
    </div>
  )
}

function AuditItem({ row }: { row: AuditRow }) {
  return (
    <li className="space-y-1 px-4 py-3 text-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="font-medium">{ACTION_LABEL[row.action] ?? row.action}</span>
        <span className="text-xs text-muted-foreground">{row.created_at ? formatTime(row.created_at) : '—'}</span>
      </div>
      <p className="text-xs text-muted-foreground">
        {row.actor ?? 'System'}
        {row.subject ? ` · ${row.subject}` : ''}
      </p>
      {Object.keys(row.details).length > 0 && (
        <p className="text-xs">{describeDetails(row.details)}</p>
      )}
    </li>
  )
}

/** Plain-language summary of what changed, e.g. "role: user → super admin". */
function describeDetails(details: Record<string, unknown>): string {
  return Object.entries(details)
    .map(([key, value]) => {
      const label = key.replace(/_/g, ' ')
      if (value && typeof value === 'object' && 'from' in value && 'to' in value) {
        const change = value as { from: unknown; to: unknown }
        return `${label}: ${String(change.from)} → ${String(change.to)}`
      }
      if (Array.isArray(value)) {
        return `${label}: ${value.join(', ') || 'none'}`
      }
      return `${label}: ${String(value)}`
    })
    .join(' · ')
}
