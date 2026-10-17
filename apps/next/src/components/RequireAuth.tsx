import type { ReactNode } from 'react'
import { Navigate, useLocation } from '@/lib/router'
import { useAuthStore } from '@/features/auth/store'

export function RequireAuth({ children }: { children: ReactNode }) {
  const token = useAuthStore((s) => s.token)
  const location = useLocation()

  if (!token) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <>{children}</>
}
