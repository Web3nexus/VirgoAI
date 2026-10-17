'use client'

import { ServerErrorPage } from '@/views/errors/ServerErrorPage'

export default function GlobalError({ reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <html lang="en">
      <body>
        <ServerErrorPage onRetry={reset} />
      </body>
    </html>
  )
}
