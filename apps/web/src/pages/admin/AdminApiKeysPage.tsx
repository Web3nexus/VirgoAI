import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { HugeiconsIcon } from '@hugeicons/react'
import { CheckmarkCircle02Icon, Key01Icon, Shield01Icon, Alert02Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useCredentials, useRemoveCredential, useSaveCredential, useTestCredential } from '@/features/admin/hooks'
import type { CredentialRow } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'

const SOURCE_LABEL: Record<CredentialRow['source'], string> = {
  database: 'Saved here (encrypted)',
  environment: 'From the server environment',
  missing: 'Not set',
}

export function AdminApiKeysPage() {
  const { data, isLoading, isError } = useCredentials()

  return (
    <div className="space-y-8">
      <PageHeader
        title="API keys"
        description="Keys for the AI providers. Saved keys are encrypted with the app key. After saving, a key is never shown again: only its last four characters."
      />

      <div className="flex items-start gap-3 rounded-xl border border-border/60 bg-card/40 p-4 text-sm">
        <HugeiconsIcon icon={Shield01Icon} size={18} strokeWidth={1.75} className="mt-0.5 shrink-0 text-muted-foreground" aria-hidden />
        <p className="text-muted-foreground">
          A key saved here overrides the server environment. Removing it falls back to the environment value, if one is set.
          Every save and removal is recorded in the <Link to="/securegate/audit" className="text-foreground underline underline-offset-4">audit log</Link>.
        </p>
      </div>

      {isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Keys could not be loaded.</p>
      ) : (
        <div className="grid gap-6">
          {data.data.map((credential) => (
            <KeyCard key={credential.name} credential={credential} />
          ))}
        </div>
      )}
    </div>
  )
}

function KeyCard({ credential }: { credential: CredentialRow }) {
  const [value, setValue] = useState('')
  const save = useSaveCredential()
  const remove = useRemoveCredential()
  const test = useTestCredential()

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate({ name: credential.name, value: value.trim() }, { onSuccess: () => setValue('') })
  }

  const valid = value.trim().length >= 12 && !/\s/.test(value.trim())

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-5 p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <span className="flex size-10 items-center justify-center rounded-xl bg-brand/15 text-brand">
              <HugeiconsIcon icon={Key01Icon} size={20} strokeWidth={1.75} aria-hidden />
            </span>
            <div>
              <h2 className="font-heading text-base font-semibold">{credential.label}</h2>
              <p className="font-mono text-xs text-muted-foreground">{credential.name}</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {credential.configured ? (
              <Badge className="gap-1">
                <HugeiconsIcon icon={CheckmarkCircle02Icon} size={12} strokeWidth={2} aria-hidden />
                Set
              </Badge>
            ) : (
              <Badge variant="destructive" className="gap-1">
                <HugeiconsIcon icon={Alert02Icon} size={12} strokeWidth={2} aria-hidden />
                Missing
              </Badge>
            )}
            <Badge variant="outline">{SOURCE_LABEL[credential.source]}</Badge>
          </div>
        </div>

        <dl className="grid gap-4 text-sm sm:grid-cols-3">
          <div>
            <dt className="text-xs text-muted-foreground">Ends with</dt>
            <dd className="font-mono">{credential.last4 ? `…${credential.last4}` : '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Last saved here</dt>
            <dd>{credential.updated_at ? formatTime(credential.updated_at) : '—'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted-foreground">Connection</dt>
            <dd>
              <Button variant="outline" size="sm" disabled={!credential.configured || test.isPending} onClick={() => test.mutate(credential.name)}>
                {test.isPending && test.variables === credential.name ? 'Testing…' : 'Test connection'}
              </Button>
            </dd>
          </div>
        </dl>

        <form onSubmit={submit} className="space-y-3 border-t border-border/60 pt-5">
          <div className="space-y-2">
            <Label htmlFor={`key-${credential.name}`}>{credential.configured ? 'Replace the key' : 'Add the key'}</Label>
            <Input
              id={`key-${credential.name}`}
              type="password"
              autoComplete="off"
              spellCheck={false}
              placeholder="Paste the key"
              value={value}
              onChange={(event) => setValue(event.target.value)}
            />
            <p className="text-xs text-muted-foreground">At least 12 characters, no spaces. The field clears after saving.</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button type="submit" disabled={!valid || save.isPending}>
              {save.isPending ? 'Saving…' : 'Save key'}
            </Button>
            {credential.source === 'database' && (
              <span className="ml-auto">
                <ConfirmButton
                  label="Remove saved key"
                  confirmLabel="Confirm remove"
                  disabled={remove.isPending}
                  onConfirm={() => remove.mutate(credential.name)}
                />
              </span>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
