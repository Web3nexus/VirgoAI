import { useState, type FormEvent } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { ConfirmButton } from '@/components/admin/AdminLayout'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useCurrentUser } from '@/features/auth/hooks'
import { useGrantTeamAccess, useRevokeTeamAccess, useTeam } from '@/features/admin/hooks'

/** Admin management: who can open the owner console. Customers are managed on User management. */
export function AdminTeamPage() {
  const { data, isLoading, isError } = useTeam()
  const { data: me } = useCurrentUser()
  const [email, setEmail] = useState('')
  const grant = useGrantTeamAccess()
  const revoke = useRevokeTeamAccess()

  function submit(event: FormEvent) {
    event.preventDefault()
    grant.mutate(email.trim(), { onSuccess: () => setEmail('') })
  }

  return (
    <div className="space-y-8">
      <PageHeader
        title="Admin management"
        description="The people who can open the owner console. Give access to an existing account. Remove it at any time. You cannot remove your own access."
      />

      <Card className="glass border-border/60">
        <CardContent className="p-5">
          <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
            <div className="min-w-64 flex-1 space-y-2">
              <Label htmlFor="team-email">Email of an existing account</Label>
              <Input id="team-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="colleague@example.com" required />
            </div>
            <Button type="submit" disabled={grant.isPending || email.trim() === ''}>
              {grant.isPending ? 'Granting…' : 'Grant admin access'}
            </Button>
          </form>
          <p className="mt-3 text-xs text-muted-foreground">Need a new account first? Create it on the User management page, then grant admin access here.</p>
        </CardContent>
      </Card>

      {isLoading ? (
        <Skeleton className="h-48 rounded-xl" />
      ) : isError || !data ? (
        <p className="text-sm text-red-300">Admins could not be loaded.</p>
      ) : (
        <ul className="divide-y divide-border/60 rounded-xl border border-border/60">
          {data.data.map((admin) => {
            const isSelf = me?.id === admin.id
            return (
              <li key={admin.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                  <p className="font-medium">
                    {admin.name}
                    {isSelf && <span className="ml-2 text-xs text-muted-foreground">(you)</span>}
                  </p>
                  <p className="text-xs text-muted-foreground">{admin.email}</p>
                </div>
                <div className="flex items-center gap-2">
                  {admin.suspended && <Badge variant="destructive">Suspended</Badge>}
                  <Badge variant="outline">Super admin</Badge>
                  {!isSelf && (
                    <ConfirmButton label="Remove access" confirmLabel="Confirm remove" disabled={revoke.isPending} onConfirm={() => revoke.mutate(admin.id)} />
                  )}
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
