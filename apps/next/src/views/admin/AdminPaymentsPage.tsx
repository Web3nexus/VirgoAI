import { useState, type FormEvent } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { Copy01Icon, Shield01Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { usePaymentsStatus, useRemoveCredential, useSaveCredential } from '@/features/admin/hooks'
import type { SecretStatus } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'

const PROVIDERS = [
  {
    key: 'flutterwave' as const,
    title: 'Flutterwave',
    description: 'Hosted checkout for cards and local methods. Recurring plans are created from the plan editor.',
    keys: ['FLUTTERWAVE_SECRET_KEY', 'FLUTTERWAVE_SECRET_HASH'],
  },
  {
    key: 'paddle' as const,
    title: 'Paddle',
    description: 'Merchant of record: Paddle collects payment and handles tax. Price ids come from your Paddle dashboard.',
    keys: ['PADDLE_API_KEY', 'PADDLE_WEBHOOK_SECRET'],
  },
]

/** Payments: keys, the webhook addresses to paste into each provider, and what the webhooks have done. */
export function AdminPaymentsPage() {
  const { data, isLoading, isError } = usePaymentsStatus()

  return (
    <div className="space-y-8">
      <PageHeader
        title="Payments"
        description="Connect Flutterwave and Paddle. Keys are write-only and encrypted. Each webhook is refused unless its provider signed it."
      />

      {isLoading ? (
        <Skeleton className="h-96 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Payment status could not be loaded.</p>
      ) : (
        <>
          <div className="grid gap-6 lg:grid-cols-2">
            {PROVIDERS.map((provider) => (
              <Card key={provider.key} className="glass border-border/60">
                <CardContent className="space-y-5 p-5">
                  <div>
                    <h2 className="font-heading text-lg font-semibold">{provider.title}</h2>
                    <p className="text-xs text-muted-foreground">{provider.description}</p>
                  </div>
                  <div className="space-y-4">
                    {data.credentials.filter((credential) => provider.keys.includes(credential.name)).map((credential) => (
                      <KeyField key={credential.name} credential={credential} />
                    ))}
                  </div>
                  <div className="space-y-2 border-t border-border/60 pt-4">
                    <p className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Webhook address</p>
                    <CopyLine value={data.webhooks[provider.key].url} />
                    <p className="text-xs text-muted-foreground">
                      Paste this into {provider.title}. Signing secret: {data.webhooks[provider.key].secret_name}.
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {data.plans.map((plan) => (
                      <Badge key={plan.key} variant={provider.key === 'flutterwave' ? (plan.flutterwave ? 'default' : 'outline') : plan.paddle ? 'default' : 'outline'}>
                        {plan.key}: {(provider.key === 'flutterwave' ? plan.flutterwave : plan.paddle) ? 'ready' : 'not set up'}
                      </Badge>
                    ))}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>

          <section aria-label="Recent webhook events" className="space-y-3">
            <div className="flex items-center gap-2">
              <HugeiconsIcon icon={Shield01Icon} size={16} strokeWidth={1.75} className="text-muted-foreground" aria-hidden />
              <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Recent webhook events</h2>
            </div>
            {data.recent_events.length === 0 ? (
              <p className="text-sm text-muted-foreground">No events yet. They appear here once a provider sends one.</p>
            ) : (
              <div className="overflow-x-auto rounded-xl border border-border/60">
                <table className="w-full text-left text-sm">
                  <thead className="bg-card/60 text-xs text-muted-foreground">
                    <tr>
                      <th className="px-4 py-2 font-medium">When</th>
                      <th className="px-4 py-2 font-medium">Provider</th>
                      <th className="px-4 py-2 font-medium">Event</th>
                      <th className="px-4 py-2 font-medium">Result</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.recent_events.map((event, index) => (
                      <tr key={`${event.at}-${index}`} className="border-t border-border/60">
                        <td className="px-4 py-2 text-muted-foreground">{event.at ? formatTime(event.at) : '—'}</td>
                        <td className="px-4 py-2 capitalize">{event.provider}</td>
                        <td className="px-4 py-2 font-mono text-xs">{event.type}</td>
                        <td className="px-4 py-2"><Badge variant={event.outcome === 'fulfilled' || event.outcome === 'duplicate' ? 'outline' : 'destructive'}>{event.outcome}</Badge></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}

function KeyField({ credential }: { credential: SecretStatus }) {
  const [value, setValue] = useState('')
  const save = useSaveCredential()
  const remove = useRemoveCredential()
  const valid = value.trim().length >= 12 && !/\s/.test(value.trim())

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate({ name: credential.name, value: value.trim() }, { onSuccess: () => setValue('') })
  }

  return (
    <form onSubmit={submit} className="space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label htmlFor={`pay-${credential.name}`}>{credential.label}</Label>
        <span className="text-xs text-muted-foreground">
          {credential.configured ? (credential.source === 'database' ? `Saved · …${credential.last4}` : 'From server environment') : 'Not set'}
        </span>
      </div>
      <div className="flex gap-2">
        <Input
          id={`pay-${credential.name}`}
          type="password"
          autoComplete="off"
          spellCheck={false}
          placeholder={credential.configured ? 'Replace the saved value' : 'Paste the value'}
          value={value}
          onChange={(event) => setValue(event.target.value)}
        />
        <Button type="submit" variant="outline" disabled={!valid || save.isPending}>Save</Button>
      </div>
      {credential.source === 'database' && (
        <ConfirmButton label="Remove saved value" confirmLabel="Confirm remove" disabled={remove.isPending} onConfirm={() => remove.mutate(credential.name)} />
      )}
    </form>
  )
}

function CopyLine({ value }: { value: string }) {
  return (
    <div className="flex items-center gap-2 rounded-lg border border-border bg-background/60 px-3 py-2">
      <code className="min-w-0 flex-1 truncate text-xs">{value}</code>
      <Button
        variant="ghost"
        size="sm"
        className="gap-1"
        onClick={() => navigator.clipboard?.writeText(value)}
        aria-label="Copy address"
      >
        <HugeiconsIcon icon={Copy01Icon} size={14} strokeWidth={1.75} aria-hidden />
        Copy
      </Button>
    </div>
  )
}
