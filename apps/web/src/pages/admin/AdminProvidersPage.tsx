import { useState } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { RefreshIcon } from '@hugeicons/core-free-icons'
import { Link } from 'react-router-dom'
import { PageHeader } from '@/components/layout/PageHeader'
import { inputClass } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdminProviders, useSyncProvider, useUpdateProvider, useUpdateProviderModel } from '@/features/admin/hooks'
import type { ProviderRow } from '@/features/admin/types'

const STATUS_OPTIONS: ProviderRow['status'][] = ['active', 'paused', 'disabled']

export function AdminProvidersPage() {
  const { data, isLoading, isError } = useAdminProviders()

  return (
    <div className="space-y-6">
      <PageHeader
        title="AI providers"
        description="Turn providers and models on or off, set their order, and re-read their model lists. Keys are added on the API keys page."
      />

      {isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Providers could not be loaded.</p>
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">No providers are registered. Run php artisan ai:sync to add them.</p>
      ) : (
        <div className="grid gap-6">
          {data.data.map((provider) => (
            <ProviderCard key={provider.key} provider={provider} />
          ))}
        </div>
      )}
    </div>
  )
}

function ProviderCard({ provider }: { provider: ProviderRow }) {
  const update = useUpdateProvider()
  const sync = useSyncProvider()
  const [priority, setPriority] = useState(String(provider.priority ?? 100))

  const ready = provider.runtime_ready
  const synced = provider.id !== null

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-5 p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-heading text-lg font-semibold">{provider.name}</h2>
              {!synced ? <Badge variant="outline">Not synced</Badge> : <Badge variant={ready ? 'default' : 'outline'}>{ready ? 'Ready' : 'Not ready'}</Badge>}
              {!provider.key_configured && <Badge variant="destructive">Key missing</Badge>}
              {!provider.adapter_registered && <Badge variant="destructive">No adapter</Badge>}
            </div>
            <p className="text-xs text-muted-foreground">
              {provider.key_configured ? 'Key is set.' : 'Key is missing.'}{' '}
              <Link to="/securegate/keys" className="text-foreground underline underline-offset-4">Manage API keys</Link>
            </p>
          </div>

          <div className="flex flex-wrap items-end gap-3">
            {synced && (<>
            <div className="space-y-1">
              <label htmlFor={`status-${provider.key}`} className="text-xs text-muted-foreground">Status</label>
              <select
                id={`status-${provider.key}`}
                className={`${inputClass} w-36 capitalize`}
                value={provider.status}
                disabled={update.isPending}
                onChange={(event) => update.mutate({ key: provider.key, patch: { status: event.target.value } })}
              >
                {STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>{status}</option>
                ))}
              </select>
            </div>
            <div className="space-y-1">
              <label htmlFor={`priority-${provider.key}`} className="text-xs text-muted-foreground">Priority (lower first)</label>
              <div className="flex gap-2">
                <input
                  id={`priority-${provider.key}`}
                  type="number"
                  min={0}
                  max={1000}
                  className={`${inputClass} w-24`}
                  value={priority}
                  onChange={(event) => setPriority(event.target.value)}
                />
                <Button
                  variant="outline"
                  size="sm"
                  disabled={update.isPending || String(provider.priority) === priority}
                  onClick={() => update.mutate({ key: provider.key, patch: { priority: Number(priority) } })}
                >
                  Save
                </Button>
              </div>
            </div>
            </>)}
            <Button variant="outline" className="gap-2" disabled={sync.isPending} onClick={() => sync.mutate(provider.key)}>
              <HugeiconsIcon icon={RefreshIcon} size={16} strokeWidth={1.75} aria-hidden />
              {sync.isPending ? 'Syncing…' : synced ? 'Sync models' : 'Sync now'}
            </Button>
          </div>
        </div>

        {!synced ? (
          <p className="text-sm text-muted-foreground">
            Not synced yet. {provider.key_configured ? 'Sync now to read its models and prices.' : `Add ${provider.credential_name ?? 'the key'} on the API keys page first, then sync.`}
          </p>
        ) : provider.models.length === 0 ? (
          <p className="text-sm text-muted-foreground">No models yet. Sync to read them from the provider.</p>
        ) : (
          <div className="overflow-x-auto rounded-lg border border-border/60">
            <table className="w-full text-left text-sm">
              <thead className="bg-card/60 text-xs text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">Model</th>
                  <th className="px-4 py-2 font-medium">Does</th>
                  <th className="px-4 py-2 font-medium">Priority</th>
                  <th className="px-4 py-2 font-medium">On</th>
                </tr>
              </thead>
              <tbody>
                {provider.models.map((model) => (
                  <ModelRow key={model.id} providerKey={provider.key} model={model} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

function ModelRow({ providerKey, model }: { providerKey: string; model: ProviderRow['models'][number] }) {
  const update = useUpdateProviderModel()

  return (
    <tr className="border-t border-border/60">
      <td className="px-4 py-2">
        <p className="font-medium">{model.name}</p>
        <p className="text-xs text-muted-foreground">{model.key}</p>
      </td>
      <td className="px-4 py-2 text-muted-foreground">{model.capabilities.join(', ') || '—'}</td>
      <td className="px-4 py-2">
        <input
          type="number"
          aria-label={`Priority for ${model.name}`}
          min={0}
          max={1000}
          className={`${inputClass} w-24`}
          defaultValue={model.priority}
          onBlur={(event) => {
            const value = Number(event.target.value)
            if (value !== model.priority) update.mutate({ key: providerKey, modelId: model.id, patch: { priority: value } })
          }}
        />
      </td>
      <td className="px-4 py-2">
        <label className="inline-flex items-center gap-2">
          <input
            type="checkbox"
            className="size-4 accent-brand"
            checked={model.enabled}
            disabled={update.isPending}
            onChange={(event) => update.mutate({ key: providerKey, modelId: model.id, patch: { enabled: event.target.checked } })}
          />
          <span className="sr-only">Enable {model.name}</span>
        </label>
      </td>
    </tr>
  )
}
