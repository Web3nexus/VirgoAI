import { useState, type FormEvent } from 'react'
import { Link, useParams } from '@/lib/router'
import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowLeft01Icon, Coins01Icon, Delete02Icon, RecycleIcon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton, Stat, inputClass } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useAdjustCredit, useAdminUser, useDeleteUser, useRestoreUser, useUpdateUser } from '@/features/admin/hooks'
import type { AdminUserDetail } from '@/features/admin/types'
import { useCurrentUser } from '@/features/auth/hooks'
import { formatTime } from '@/features/workflows/timeline'
import { usd } from '@/lib/money'

const LEDGER_LABEL: Record<string, string> = {
  grant: 'Credit added',
  charge: 'Run charged',
  refund: 'Refund',
  settlement: 'Settled',
  adjustment: 'Adjustment',
}

export function AdminUserPage() {
  const { id = '' } = useParams()
  const { data: me } = useCurrentUser()
  const { data: user, isLoading, isError } = useAdminUser(id)

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !user) return <p className="text-sm text-red-300">That user could not be loaded.</p>

  const isSelf = me?.id === user.id

  return (
    <div className="space-y-8">
      <Link to="/securegate/users" className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <HugeiconsIcon icon={ArrowLeft01Icon} size={16} strokeWidth={1.75} aria-hidden /> Users
      </Link>

      <PageHeader title={user.name} description={user.email} />

      <div className="flex flex-wrap gap-2">
        {user.role === 'super_admin' && <Badge>Super admin</Badge>}
        {user.deleted && <Badge variant="outline">Deleted</Badge>}
        {user.suspended && <Badge variant="destructive">Suspended</Badge>}
        {isSelf && <Badge variant="outline">This is you</Badge>}
      </div>

      <section aria-label="Account" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Balance" value={usd(user.balance_usd, 4)} />
        <Stat label="Paid for runs" value={usd(user.totals.paid_usd)} hint={`${user.totals.succeeded} finished · ${user.totals.failed} failed`} />
        <Stat label="Runs" value={String(user.runs)} />
        <Stat label="Joined" value={user.created_at ? formatTime(user.created_at) : '—'} />
      </section>

      <div className="grid gap-6 lg:grid-cols-2">
        <ProfileForm key={`${user.id}-${user.name}-${user.email}`} user={user} />
        <CreditForm userId={id} />
        <AccessCard key={`${user.id}-${user.role}-${user.suspended}`} user={user} isSelf={isSelf} />
      </div>

      <section aria-label="Wallet history" className="space-y-3">
        <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Wallet history</h2>
        {user.ledger.length === 0 ? (
          <p className="text-sm text-muted-foreground">No credit has moved yet.</p>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-border/60">
            <table className="w-full text-left text-sm">
              <thead className="bg-card/60 text-xs text-muted-foreground">
                <tr>
                  <th className="px-4 py-2 font-medium">When</th>
                  <th className="px-4 py-2 font-medium">What</th>
                  <th className="px-4 py-2 font-medium">Amount</th>
                  <th className="px-4 py-2 font-medium">Balance after</th>
                  <th className="px-4 py-2 font-medium">Note</th>
                </tr>
              </thead>
              <tbody>
                {user.ledger.map((entry) => (
                  <tr key={entry.id} className="border-t border-border/60">
                    <td className="px-4 py-2 text-muted-foreground">{entry.created_at ? formatTime(entry.created_at) : '—'}</td>
                    <td className="px-4 py-2">{LEDGER_LABEL[entry.type] ?? entry.type}</td>
                    <td className={entry.amount_usd < 0 ? 'px-4 py-2 text-red-300' : 'px-4 py-2 text-emerald-300'}>{usd(entry.amount_usd, 4)}</td>
                    <td className="px-4 py-2">{usd(entry.balance_after_usd, 4)}</td>
                    <td className="px-4 py-2 text-muted-foreground">{entry.note ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      <section aria-label="Recent runs" className="space-y-3">
        <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Recent runs</h2>
        {user.recent_runs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No runs yet.</p>
        ) : (
          <ul className="divide-y divide-border/60 rounded-xl border border-border/60">
            {user.recent_runs.map((run) => (
              <li key={run.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                <span className="capitalize">{run.tool}</span>
                <span className="text-muted-foreground">{run.model_key ?? 'No model'}</span>
                <span>{run.status}</span>
                <span>{usd(run.price_usd, 4)}</span>
                <span className="text-muted-foreground">{run.created_at ? formatTime(run.created_at) : '—'}</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}

function ProfileForm({ user }: { user: AdminUserDetail }) {
  const [name, setName] = useState(user.name)
  const [email, setEmail] = useState(user.email)
  const update = useUpdateUser(String(user.id))

  function submit(event: FormEvent) {
    event.preventDefault()
    update.mutate({ name, email })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div>
          <h2 className="font-heading text-base font-semibold">Profile</h2>
          <p className="text-xs text-muted-foreground">Name and email the user signs in with.</p>
        </div>
        <form onSubmit={submit} className="space-y-3">
          <div className="space-y-2">
            <Label htmlFor="profile-name">Name</Label>
            <Input id="profile-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={120} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="profile-email">Email</Label>
            <Input id="profile-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <Button type="submit" disabled={update.isPending || (name === user.name && email === user.email)}>
            {update.isPending ? 'Saving…' : 'Save profile'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function CreditForm({ userId }: { userId: string }) {
  const [amount, setAmount] = useState('')
  const [note, setNote] = useState('')
  const adjust = useAdjustCredit(userId)

  function submit(event: FormEvent) {
    event.preventDefault()
    adjust.mutate({ amount_usd: Number(amount), note }, { onSuccess: () => { setAmount(''); setNote('') } })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div>
          <h2 className="font-heading text-base font-semibold">Add or remove credit</h2>
          <p className="text-xs text-muted-foreground">Use a negative amount to remove credit. The balance cannot go below zero. Added credit is emailed to the user.</p>
        </div>
        <form onSubmit={submit} className="space-y-3">
          <div className="space-y-2">
            <Label htmlFor="credit-amount">Amount (USD)</Label>
            <Input id="credit-amount" type="number" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="credit-note">Reason</Label>
            <Input id="credit-note" value={note} onChange={(e) => setNote(e.target.value)} placeholder="e.g. Launch credit" minLength={3} required />
          </div>
          <Button type="submit" className="gap-2" disabled={adjust.isPending || amount === '' || Number(amount) === 0}>
            <HugeiconsIcon icon={Coins01Icon} size={16} strokeWidth={1.75} aria-hidden />
            {adjust.isPending ? 'Saving…' : 'Apply'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}

function AccessCard({ user, isSelf }: { user: AdminUserDetail; isSelf: boolean }) {
  const update = useUpdateUser(String(user.id))
  const remove = useDeleteUser()
  const restore = useRestoreUser()
  const [role, setRole] = useState(user.role)

  if (user.deleted) {
    return (
      <Card className="glass border-border/60">
        <CardContent className="space-y-4 p-5">
          <div>
            <h2 className="font-heading text-base font-semibold">Deleted account</h2>
            <p className="text-xs text-muted-foreground">This user cannot sign in. Their wallet and run history are kept.</p>
          </div>
          <Button className="gap-2" disabled={restore.isPending} onClick={() => restore.mutate(user.id)}>
            <HugeiconsIcon icon={RecycleIcon} size={16} strokeWidth={1.75} aria-hidden />
            Restore account
          </Button>
        </CardContent>
      </Card>
    )
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-5 p-5">
        <div>
          <h2 className="font-heading text-base font-semibold">Role and access</h2>
          <p className="text-xs text-muted-foreground">You cannot demote, suspend, or delete your own account.</p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="user-role">Role</Label>
          <select id="user-role" className={inputClass} value={role} onChange={(e) => setRole(e.target.value)} disabled={isSelf}>
            <option value="user">User</option>
            <option value="super_admin">Super admin</option>
          </select>
          <Button variant="outline" size="sm" disabled={isSelf || role === user.role || update.isPending} onClick={() => update.mutate({ role })}>
            Save role
          </Button>
        </div>

        <div className="space-y-2">
          <p className="text-sm">{user.suspended ? 'Suspended. This account cannot sign in or run tools.' : 'Active.'}</p>
          <Button variant={user.suspended ? 'outline' : 'destructive'} size="sm" disabled={isSelf || update.isPending} onClick={() => update.mutate({ suspended: !user.suspended })}>
            {user.suspended ? 'Reactivate account' : 'Suspend account'}
          </Button>
        </div>

        <div className="space-y-2 border-t border-border/60 pt-4">
          <p className="text-sm">Delete keeps the wallet and run history. The user cannot sign in afterwards.</p>
          <ConfirmButton
            label="Delete account"
            confirmLabel="Confirm delete"
            icon={Delete02Icon}
            disabled={isSelf || remove.isPending}
            onConfirm={() => remove.mutate(user.id)}
          />
        </div>
      </CardContent>
    </Card>
  )
}
