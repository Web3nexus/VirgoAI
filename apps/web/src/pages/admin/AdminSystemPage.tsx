import { useState, type FormEvent } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { MailSend01Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { Stat } from '@/components/admin/AdminLayout'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { inputClass } from '@/components/admin/AdminLayout'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useAdminSystem, useSendTestEmail, useUpdateSystem } from '@/features/admin/hooks'
import type { SecretStatus, SystemSettings } from '@/features/admin/types'
import { percent, usd } from '@/lib/money'

export function AdminSystemPage() {
  const { data, isLoading, isError } = useAdminSystem()

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !data) return <p className="text-sm text-red-300">System settings could not be loaded.</p>

  return (
    <div className="space-y-8">
      <PageHeader title="Settings" description="Switches for the whole platform. Changes take effect immediately." />

      <SettingsForm key={JSON.stringify(data.settings) + data.mail_password.updated_at} settings={data.settings} mailPassword={data.mail_password} />

      <section aria-label="Runtime" className="space-y-3">
        <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Runtime (set on the server)</h2>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <Stat label="Anthropic" value={data.runtime.anthropic_ready ? 'Ready' : 'Not ready'} hint="Needs ANTHROPIC_API_KEY" />
          <Stat label="fal.ai" value={data.runtime.fal_ready ? 'Ready' : 'Not ready'} hint="Needs FAL_KEY" />
          <Stat label="Your margin" value={percent(data.runtime.margin_percent)} hint="Change it on the Pricing page" />
          <Stat label="Queue" value={data.runtime.queue_connection} hint={`Video renders wait up to ${Math.round(data.runtime.generation_max_wait_seconds / 60)} min`} />
          <Stat label="Mail" value={data.runtime.mail_mailer} />
          <Stat label="Poll every" value={`${data.runtime.fal_poll_interval_seconds}s`} hint="How often queued renders are checked" />
          <Stat label="Claude reply limit" value={`${data.runtime.anthropic_max_tokens} tokens`} />
          <Stat label="Environment" value={data.runtime.app_env} />
        </div>
        <p className="text-xs text-muted-foreground">A $1.00 provider cost is sold for {usd(1 + data.runtime.margin_percent / 100)} at the current margin.</p>
      </section>
    </div>
  )
}

function SettingsForm({ settings, mailPassword }: { settings: SystemSettings; mailPassword: SecretStatus }) {
  const [draft, setDraft] = useState(settings)
  const [password, setPassword] = useState('')
  const update = useUpdateSystem()
  const testEmail = useSendTestEmail()
  const [testResult, setTestResult] = useState<string | null>(null)

  const dirty = JSON.stringify(draft) !== JSON.stringify(settings) || password !== ''

  function submit(event: FormEvent) {
    event.preventDefault()
    update.mutate(password ? { ...draft, mail_password: password } : draft, { onSuccess: () => setPassword('') })
  }

  function sendTest() {
    testEmail.mutate(undefined, { onSuccess: (result) => setTestResult(result.message) })
  }

  return (
    <form onSubmit={submit} className="space-y-6">
      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="glass border-border/60 lg:col-span-2">
          <CardContent className="space-y-5 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="font-heading text-base font-semibold">Email engine</h2>
                <p className="text-xs text-muted-foreground">
                  The log driver writes emails to the log and delivers nothing. Choose SMTP to send for real.
                </p>
              </div>
              <Button type="button" variant="outline" className="gap-2" disabled={testEmail.isPending} onClick={sendTest}>
                <HugeiconsIcon icon={MailSend01Icon} size={16} strokeWidth={1.75} aria-hidden />
                {testEmail.isPending ? 'Sending…' : 'Send test email'}
              </Button>
            </div>
            {testResult && <p role="status" className="rounded-lg border border-border/60 bg-background/40 px-3 py-2 text-sm">{testResult}</p>}
            <div className="grid gap-4 md:grid-cols-3">
              <div className="space-y-2">
                <Label htmlFor="mail-driver">Driver</Label>
                <select id="mail-driver" className={inputClass} value={draft.mail_driver} onChange={(e) => setDraft({ ...draft, mail_driver: e.target.value as SystemSettings['mail_driver'] })}>
                  <option value="log">Log only (no delivery)</option>
                  <option value="smtp">SMTP server</option>
                </select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="mail-host">SMTP host</Label>
                <Input id="mail-host" value={draft.mail_host} onChange={(e) => setDraft({ ...draft, mail_host: e.target.value })} placeholder="smtp.example.com" disabled={draft.mail_driver !== 'smtp'} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="mail-port">Port</Label>
                  <Input id="mail-port" type="number" min={1} max={65535} value={draft.mail_port} onChange={(e) => setDraft({ ...draft, mail_port: Number(e.target.value) })} disabled={draft.mail_driver !== 'smtp'} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="mail-encryption">Encryption</Label>
                  <select id="mail-encryption" className={inputClass} value={draft.mail_encryption} onChange={(e) => setDraft({ ...draft, mail_encryption: e.target.value as SystemSettings['mail_encryption'] })} disabled={draft.mail_driver !== 'smtp'}>
                    <option value="tls">TLS (587)</option>
                    <option value="ssl">SSL (465)</option>
                    <option value="none">None</option>
                  </select>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="mail-username">Username</Label>
                <Input id="mail-username" value={draft.mail_username} onChange={(e) => setDraft({ ...draft, mail_username: e.target.value })} disabled={draft.mail_driver !== 'smtp'} autoComplete="off" />
              </div>
              <div className="space-y-2">
                <Label htmlFor="mail-password">Password</Label>
                <Input id="mail-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} disabled={draft.mail_driver !== 'smtp'} placeholder={mailPassword.configured ? `Saved, ends with …${mailPassword.last4 ?? ''}` : 'Not set'} />
                <p className="text-xs text-muted-foreground">Write-only. Stored encrypted. Leave blank to keep the saved one.</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="space-y-5 p-5">
            <h2 className="font-heading text-base font-semibold">Access</h2>
            <label className="flex items-start gap-3 text-sm">
              <input
                type="checkbox"
                className="mt-1 size-4 accent-brand"
                checked={draft.registration_enabled}
                onChange={(e) => setDraft({ ...draft, registration_enabled: e.target.checked })}
              />
              <span>
                <span className="font-medium">Allow new sign-ups</span>
                <span className="block text-xs text-muted-foreground">When off, the sign-up page refuses new accounts. Existing users are not affected.</span>
              </span>
            </label>
            <div className="space-y-2">
              <Label htmlFor="welcome-credit">Welcome credit for new users (USD)</Label>
              <Input
                id="welcome-credit"
                type="number"
                min={0}
                max={100}
                step="0.01"
                value={draft.welcome_credit_usd}
                onChange={(e) => setDraft({ ...draft, welcome_credit_usd: Number(e.target.value) })}
              />
              <p className="text-xs text-muted-foreground">Added to the wallet when someone signs up. Set 0 for no credit.</p>
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="space-y-5 p-5">
            <h2 className="font-heading text-base font-semibold">Maintenance</h2>
            <label className="flex items-start gap-3 text-sm">
              <input
                type="checkbox"
                className="mt-1 size-4 accent-brand"
                checked={draft.maintenance_mode}
                onChange={(e) => setDraft({ ...draft, maintenance_mode: e.target.checked })}
              />
              <span>
                <span className="font-medium">Pause all runs for users</span>
                <span className="block text-xs text-muted-foreground">Users see the message below. You can still run tools to test.</span>
              </span>
            </label>
            <div className="space-y-2">
              <Label htmlFor="maintenance-message">Message to users</Label>
              <Textarea
                id="maintenance-message"
                rows={3}
                maxLength={255}
                value={draft.maintenance_message}
                onChange={(e) => setDraft({ ...draft, maintenance_message: e.target.value })}
              />
            </div>
          </CardContent>
        </Card>

        <Card className="glass border-border/60">
          <CardContent className="space-y-5 p-5">
            <h2 className="font-heading text-base font-semibold">Email identity</h2>
            <div className="space-y-2">
              <Label htmlFor="from-name">Sender name</Label>
              <Input id="from-name" maxLength={120} value={draft.email_from_name} onChange={(e) => setDraft({ ...draft, email_from_name: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="from-address">Sender address</Label>
              <Input id="from-address" type="email" value={draft.email_from_address} onChange={(e) => setDraft({ ...draft, email_from_address: e.target.value })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="support">Support address (shown in emails)</Label>
              <Input id="support" type="email" value={draft.support_email} onChange={(e) => setDraft({ ...draft, support_email: e.target.value })} />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={!dirty || update.isPending}>{update.isPending ? 'Saving…' : 'Save settings'}</Button>
        {dirty && (
          <Button type="button" variant="ghost" onClick={() => setDraft(settings)}>Discard changes</Button>
        )}
      </div>
    </form>
  )
}
