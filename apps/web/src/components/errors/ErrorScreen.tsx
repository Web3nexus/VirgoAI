import type { ReactNode } from 'react'

/** The shared layout for every error page: the status code, a short title, one plain sentence, and the next step. */
export function ErrorScreen({ code, title, message, children }: { code: string; title: string; message: string; children?: ReactNode }) {
  return (
    <div className="grid min-h-svh place-items-center bg-background px-4 text-center text-foreground">
      <div className="max-w-md space-y-4">
        <p className="font-heading text-6xl font-semibold text-brand">{code}</p>
        <h1 className="text-xl font-semibold">{title}</h1>
        <p className="text-sm leading-6 text-muted-foreground">{message}</p>
        {children && <div className="flex flex-wrap items-center justify-center gap-3 pt-2">{children}</div>}
      </div>
    </div>
  )
}
