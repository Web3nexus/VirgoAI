'use client'

import { useEffect } from 'react'
import { ServerErrorPage } from '@/views/errors/ServerErrorPage'

/** Catches a crash while drawing a page, so the user sees the error page instead of a blank screen. */
export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error('The page failed to render.', error)
  }, [error])
  return <ServerErrorPage onRetry={reset} />
}
