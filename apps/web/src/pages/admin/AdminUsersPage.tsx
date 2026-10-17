import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { HugeiconsIcon } from '@hugeicons/react'
import { Add01Icon, ArrowRight01Icon, Search01Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton, inputClass } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useCreateUser, useAdminUsers, useDeleteUser, useRestoreUser } from '@/features/admin/hooks'
import type { UserStatusFilter } from '@/features/admin/types'
import { usd } from '@/lib/money'
import { cn } from '@/lib/utils'

const FILTERS: { value: UserStatusFilter; label: string }[] = [
  { value: 'active', label: 'Active' },
  { value: 'suspended', label: 'Suspended' },
  { value: 'deleted', label: 'Deleted' },
]

export function AdminUsersPage() {
  const [query, setQuery] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<UserStatusFilter>('active')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const { data, isLoading, isError } = useAdminUsers(search, page, status)
  const remove = useDeleteUser()
  const restore = useRestoreUser()

  function submit(event: FormEvent) {
    event.preventDefault()
    setPage(1)
    setSearch(query.trim())
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="User management"
        description="Customer accounts: create, edit, suspend, delete, and restore. Deleting keeps a user's wallet and run history. Admin access is on Admin management."
      />

      <div className="flex flex-wrap items-center justify-between gap-3">
        <form onSubmit={submit} className="flex max-w-md flex-1 gap-2">
          <div className="relative flex-1">
            <HugeiconsIcon icon={Search01Icon} size={16} strokeWidth={1.75} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
            <Input className="pl-9" placeholder="Search by name or email" value={query} onChange={(e) => setQuery(e.target.value)} aria-label="Search users" />
          </div>
          <Button type="submit" variant="outline">Search</Button>
        </form>
        <div className="flex items-center gap-2">
          <div role="tablist" aria-label="User status" className="flex rounded-lg border border-border p-0.5">
            {FILTERS.map((item) => (
              <button
                key={item.value}
                type="button"
                role="tab"
                aria-selected={status === item.value}
                onClick={() => {
                  setStatus(item.value)
                  setPage(1)
                }}
                className={cn(
                  'rounded-md px-3 py-1 text-xs transition-colors',
                  status === item.value ? 'bg-sidebar-accent text-foreground' : 'text-muted-foreground hover:text-foreground',
                )}
              >
                {item.label}
              </button>
            ))}
          </div>
          <Button className="gap-2" onClick={() => setCreating((value) => !value)}>
            <HugeiconsIcon icon={Add01Icon} size={16} strokeWidth={1.75} aria-hidden />
            New user
          </Button>
        </div>
      </div>

      {creating && <CreateUserForm onDone={() => setCreating(false)} />}

      {isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Users could not be loaded.</p>
      ) : data.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">No users match.</p>
      ) : (
        <div className="overflow-x-auto rounded-xl border border-border/60">
          <table className="w-full text-left text-sm">
            <thead className="bg-card/60 text-xs text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Name</th>
                <th className="px-4 py-2 font-medium">Email</th>
                <th className="px-4 py-2 font-medium">Role</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Balance</th>
                <th className="px-4 py-2 font-medium">Runs</th>
                <th className="px-4 py-2 text-right font-medium"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((user) => (
                <tr key={user.id} className="border-t border-border/60 hover:bg-card/40">
                  <td className="px-4 py-2">
                    <Link to={`/securegate/users/${user.id}`} className="font-medium hover:text-brand">
                      {user.name}
                    </Link>
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{user.email}</td>
                  <td className="px-4 py-2">{user.role === 'super_admin' ? 'Super admin' : 'User'}</td>
                  <td className="px-4 py-2">
                    {user.deleted ? <Badge variant="outline">Deleted</Badge> : user.suspended ? <Badge variant="destructive">Suspended</Badge> : 'Active'}
                  </td>
                  <td className="px-4 py-2">{usd(user.balance_usd)}</td>
                  <td className="px-4 py-2">{user.runs}</td>
                  <td className="px-4 py-2 text-right">
                    {user.deleted ? (
                      <Button variant="outline" size="sm" disabled={restore.isPending} onClick={() => restore.mutate(user.id)}>
                        Restore
                      </Button>
                    ) : (
                      <span className="inline-flex items-center gap-2">
                        <Link to={`/securegate/users/${user.id}`} className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                          Open
                          <HugeiconsIcon icon={ArrowRight01Icon} size={14} strokeWidth={1.75} aria-hidden />
                        </Link>
                        <ConfirmButton
                          label="Delete"
                          confirmLabel="Confirm delete"
                          disabled={remove.isPending}
                          onConfirm={() => remove.mutate(user.id)}
                        />
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2 text-sm">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <span className="text-muted-foreground">Page {data.meta.current_page} of {data.meta.last_page}</span>
          <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </div>
  )
}

function CreateUserForm({ onDone }: { onDone: () => void }) {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState('user')
  const create = useCreateUser()

  function submit(event: FormEvent) {
    event.preventDefault()
    create.mutate({ name, email, password, role }, { onSuccess: onDone })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="p-5">
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label htmlFor="new-name">Name</Label>
            <Input id="new-name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="new-email">Email</Label>
            <Input id="new-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="new-password">Temporary password (at least 12 characters)</Label>
            <Input id="new-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={12} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="new-role">Role</Label>
            <select id="new-role" className={inputClass} value={role} onChange={(e) => setRole(e.target.value)}>
              <option value="user">User</option>
              <option value="super_admin">Super admin</option>
            </select>
          </div>
          <div className="flex gap-2 md:col-span-2">
            <Button type="submit" disabled={create.isPending}>{create.isPending ? 'Creating…' : 'Create user'}</Button>
            <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
