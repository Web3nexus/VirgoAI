import { useState, type FormEvent } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { Add01Icon } from '@hugeicons/core-free-icons'
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
  useCreateEmailTemplate,
  useDeleteEmailTemplate,
  useEmailLogs,
  useEmailTemplates,
  useUpdateEmailTemplate,
} from '@/features/admin/hooks'
import type { EmailTemplateRow } from '@/features/admin/types'
import { formatTime } from '@/features/workflows/timeline'

const KEY_PATTERN = /^[a-z_]+$/

export function AdminEmailPage() {
  const { data, isLoading, isError } = useEmailTemplates()
  const [selectedId, setSelectedId] = useState<number | 'new' | null>(null)
  const [tab, setTab] = useState<'templates' | 'log'>('templates')

  const selected = data?.data.find((template) => template.id === selectedId) ?? null

  return (
    <div className="space-y-8">
      <PageHeader title="Email templates" description="What each email says, and whether it went out. The mail server is set on Settings." />

      {isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Email settings could not be loaded.</p>
      ) : (
        <>
          <section aria-label="Sending" className="grid gap-4 sm:grid-cols-3">
            <Stat label="Sends from" value={data.from.name} hint={data.from.address} />
            <Stat label="Delivery" value={data.mailer === 'smtp' ? 'SMTP' : 'Log only'} hint="Set the email engine and send a test on Settings" />
            <Stat label="Placeholders" value={data.placeholders.map((item) => `{{${item}}}`).join(' ')} />
          </section>

          <div role="tablist" aria-label="Email sections" className="flex w-fit rounded-lg border border-border p-0.5">
            {(['templates', 'log'] as const).map((item) => (
              <button
                key={item}
                type="button"
                role="tab"
                aria-selected={tab === item}
                onClick={() => setTab(item)}
                className={`rounded-md px-3 py-1 text-xs transition-colors ${tab === item ? 'bg-sidebar-accent text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
              >
                {item === 'templates' ? 'Templates' : 'Sent log'}
              </button>
            ))}
          </div>

          {tab === 'templates' ? (
            <div className="grid gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
              <Card className="glass h-fit border-border/60">
                <CardContent className="space-y-2 p-3">
                  <Button variant="outline" className="w-full justify-start gap-2" onClick={() => setSelectedId('new')}>
                    <HugeiconsIcon icon={Add01Icon} size={16} strokeWidth={1.75} aria-hidden />
                    New template
                  </Button>
                  <ul className="space-y-1">
                    {data.data.map((template) => (
                      <li key={template.id}>
                        <button
                          type="button"
                          onClick={() => setSelectedId(template.id)}
                          className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-sidebar-accent ${selectedId === template.id ? 'bg-sidebar-accent' : ''}`}
                        >
                          <span className="truncate">{template.name}</span>
                          {!template.enabled && <Badge variant="outline">Off</Badge>}
                        </button>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>

              <div>
                {selectedId === 'new' ? (
                  <TemplateForm key="new" placeholders={data.placeholders} onDone={() => setSelectedId(null)} />
                ) : selected ? (
                  <TemplateForm key={selected.id} template={selected} placeholders={data.placeholders} onDone={() => setSelectedId(null)} />
                ) : (
                  <p className="text-sm text-muted-foreground">Choose a template to edit, or create a new one.</p>
                )}
              </div>
            </div>
          ) : (
            <EmailLog />
          )}
        </>
      )}
    </div>
  )
}

function TemplateForm({
  template,
  placeholders,
  onDone,
}: {
  template?: EmailTemplateRow
  placeholders: string[]
  onDone: () => void
}) {
  const [key, setKey] = useState(template?.key ?? '')
  const [name, setName] = useState(template?.name ?? '')
  const [subject, setSubject] = useState(template?.subject ?? '')
  const [body, setBody] = useState(template?.body ?? '')
  const [enabled, setEnabled] = useState(template?.enabled ?? true)
  const create = useCreateEmailTemplate()
  const update = useUpdateEmailTemplate()
  const remove = useDeleteEmailTemplate()

  const keyValid = template ? true : KEY_PATTERN.test(key)
  const busy = create.isPending || update.isPending

  function submit(event: FormEvent) {
    event.preventDefault()
    if (template) {
      update.mutate({ id: template.id, payload: { name, subject, body, enabled } }, { onSuccess: onDone })
    } else {
      create.mutate({ key, name, subject, body, enabled }, { onSuccess: onDone })
    }
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="p-5">
        <form onSubmit={submit} className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="tpl-name">Name</Label>
              <Input id="tpl-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="tpl-key">Key</Label>
              <Input
                id="tpl-key"
                value={key}
                onChange={(e) => setKey(e.target.value)}
                disabled={Boolean(template)}
                required
                placeholder="lowercase_with_underscores"
                aria-invalid={!keyValid || undefined}
              />
              {!keyValid && <p className="text-xs text-red-300">Use lowercase letters and underscores only.</p>}
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="tpl-subject">Subject</Label>
            <Input id="tpl-subject" value={subject} onChange={(e) => setSubject(e.target.value)} required maxLength={200} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="tpl-body">Body</Label>
            <Textarea id="tpl-body" rows={8} value={body} onChange={(e) => setBody(e.target.value)} required maxLength={10000} />
            <p className="text-xs text-muted-foreground">Placeholders: {placeholders.map((item) => `{{${item}}}`).join(', ')}</p>
          </div>
          <label className="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 accent-brand" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
            Send this email
          </label>

          <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-4">
            <Button type="submit" disabled={busy || !keyValid}>{busy ? 'Saving…' : template ? 'Save template' : 'Create template'}</Button>
            <Button type="button" variant="ghost" onClick={onDone}>Close</Button>
            {template && (
              <>
                <span className="ml-auto">
                  <ConfirmButton
                    label="Delete template"
                    confirmLabel="Confirm delete"
                    disabled={remove.isPending}
                    onConfirm={() => remove.mutate(template.id, { onSuccess: onDone })}
                  />
                </span>
              </>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function EmailLog() {
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useEmailLogs(page)

  if (isLoading) return <Skeleton className="h-64 rounded-xl" />
  if (isError || !data) return <p className="text-sm text-red-300">The log could not be loaded.</p>
  if (data.data.length === 0) return <p className="text-sm text-muted-foreground">Nothing has been sent yet.</p>

  return (
    <div className="space-y-4">
      <div className="overflow-x-auto rounded-xl border border-border/60">
        <table className="w-full text-left text-sm">
          <thead className="bg-card/60 text-xs text-muted-foreground">
            <tr>
              <th className="px-4 py-2 font-medium">When</th>
              <th className="px-4 py-2 font-medium">To</th>
              <th className="px-4 py-2 font-medium">Subject</th>
              <th className="px-4 py-2 font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            {data.data.map((row) => (
              <tr key={row.id} className="border-t border-border/60">
                <td className="px-4 py-2 text-muted-foreground">{row.created_at ? formatTime(row.created_at) : '—'}</td>
                <td className="px-4 py-2">{row.to}</td>
                <td className="px-4 py-2">{row.subject}</td>
                <td className="px-4 py-2">
                  <Badge variant={row.status === 'failed' ? 'destructive' : 'outline'}>{row.status}</Badge>
                  {row.error && <p className="mt-1 text-xs text-red-300">{row.error}</p>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="flex items-center justify-end gap-2 text-sm">
        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Newer</Button>
        <span className="text-muted-foreground">Page {data.meta.current_page} of {data.meta.last_page}</span>
        <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>Older</Button>
      </div>
    </div>
  )
}
