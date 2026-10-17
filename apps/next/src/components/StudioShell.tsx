'use client'

import { useEffect, useState, type ReactNode } from 'react'
import { AppShell } from '@/components/layout/AppShell'
import { RequireAuth } from '@/components/RequireAuth'

/**
 * The signed-in studio. It draws only after the browser has loaded, because the session lives in the browser.
 * Drawing it on the server would show a signed-out page first, then a different one.
 */
export function StudioShell({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false)
  useEffect(() => setReady(true), [])

  if (!ready) return null

  return (
    <RequireAuth>
      <AppShell>{children}</AppShell>
    </RequireAuth>
  )
}
