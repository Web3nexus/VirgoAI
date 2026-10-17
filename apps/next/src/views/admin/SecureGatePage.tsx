import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@/lib/router'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { AdminLayout } from '@/components/admin/AdminLayout'
import { adminLogin } from '@/features/admin/api'
import { useCurrentUser } from '@/features/auth/hooks'
import { useAuthStore } from '@/features/auth/store'
import { apiErrorMessage } from '@/lib/api'

/**
 * /securegate. Signed out or not a super admin: the sign-in form. A super admin: the owner console.
 * Non-admins see the same form and the same error, so the page does not reveal who the admins are.
 */
export function SecureGatePage({ children }: { children: ReactNode }) {
  const token = useAuthStore((state) => state.token)
  const { data: user, isLoading } = useCurrentUser()

  useNoIndex()

  if (!token) return <SecureLogin />
  if (isLoading) return <Skeleton className="m-8 h-64 rounded-xl" />
  if (!user?.is_super_admin) return <SecureLogin />

  return <AdminLayout>{children}</AdminLayout>
}

function SecureLogin() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const setSession = useAuthStore((state) => state.setSession)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')

  const login = useMutation({
    mutationFn: adminLogin,
    onSuccess: (session) => {
      setSession(session.user, session.token)
      queryClient.removeQueries({ queryKey: ['auth', 'me'] })
      navigate('/securegate')
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    login.mutate({ email, password })
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-6">
      <Card className="glass w-full max-w-sm border-border/60">
        <CardContent className="space-y-6 p-6">
          <div>
            <p className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Owner console</p>
            <h1 className="mt-1 font-heading text-xl font-semibold">Sign in</h1>
          </div>
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="admin-email">Email</Label>
              <Input id="admin-email" type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </div>
            <div className="space-y-2">
              <Label htmlFor="admin-password">Password</Label>
              <Input id="admin-password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            </div>
            {login.isError && <p role="alert" className="text-sm text-red-300">{apiErrorMessage(login.error)}</p>}
            <Button type="submit" className="w-full" disabled={login.isPending}>
              {login.isPending ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

/** Keeps the owner console out of search engines. */
function useNoIndex() {
  useEffect(() => {
    const previous = document.title
    document.title = 'Sign in · virgoAi'
    let meta = document.querySelector<HTMLMetaElement>('meta[name="robots"]')
    const created = !meta
    if (!meta) {
      meta = document.createElement('meta')
      meta.name = 'robots'
      document.head.appendChild(meta)
    }
    meta.content = 'noindex, nofollow'

    return () => {
      document.title = previous
      if (created) meta?.remove()
    }
  }, [])
}
