import { useState } from 'react'
import { cn } from '@/lib/utils'

// Keys the engine adds for its own bookkeeping. They are not shown as results.
const BOOKKEEPING_KEYS = new Set(['stage', 'placeholder', 'generated_at', 'depends_on', 'summary'])

function humanize(key: string): string {
  const words = key.replace(/_/g, ' ')
  return words.charAt(0).toUpperCase() + words.slice(1)
}

function Value({ value }: { value: unknown }) {
  if (value === null || value === undefined || value === '') {
    return <span className="text-muted-foreground">—</span>
  }
  if (typeof value !== 'object') {
    return <span>{String(value)}</span>
  }
  if (Array.isArray(value)) {
    if (value.length === 0) return <span className="text-muted-foreground">None</span>
    return (
      <ul className="list-disc space-y-0.5 pl-4">
        {value.map((item, index) => (
          <li key={index}>
            <Value value={item} />
          </li>
        ))}
      </ul>
    )
  }
  return (
    <dl className="space-y-1.5">
      {Object.entries(value as Record<string, unknown>).map(([key, nested]) => (
        <div key={key}>
          <dt className="text-[11px] uppercase tracking-wide text-muted-foreground">{humanize(key)}</dt>
          <dd>
            <Value value={nested} />
          </dd>
        </div>
      ))}
    </dl>
  )
}

/**
 * Shows a stage result in plain language. Raw JSON stays available behind a toggle for
 * debugging. Placeholder output says plainly that nothing was generated.
 */
export function OutputView({ output }: { output: Record<string, unknown> | null | undefined }) {
  const [showRaw, setShowRaw] = useState(false)

  if (!output) {
    return <p className="text-muted-foreground">Nothing has been produced yet.</p>
  }

  const isPlaceholder = output.placeholder === true
  const results = Object.entries(output).filter(([key]) => !BOOKKEEPING_KEYS.has(key))

  return (
    <div className="space-y-3">
      {isPlaceholder ? (
        <p className="text-amber-300">
          Placeholder. This stage is not connected to a generator yet, so no content was created.
        </p>
      ) : (
        typeof output.summary === 'string' && <p>{output.summary}</p>
      )}

      {results.length > 0 && !isPlaceholder && (
        <dl className="space-y-2">
          {results.map(([key, value]) => (
            <div key={key}>
              <dt className="text-[11px] uppercase tracking-wide text-muted-foreground">{humanize(key)}</dt>
              <dd>
                <Value value={value} />
              </dd>
            </div>
          ))}
        </dl>
      )}

      <button
        type="button"
        onClick={() => setShowRaw((value) => !value)}
        className={cn('text-[11px] text-muted-foreground underline-offset-2 hover:underline')}
        aria-expanded={showRaw}
      >
        {showRaw ? 'Hide technical details' : 'Show technical details'}
      </button>
      {showRaw && (
        <pre className="max-h-48 overflow-auto rounded-lg bg-ink-2 p-3 text-muted-foreground">
          {JSON.stringify(output, null, 2)}
        </pre>
      )}
    </div>
  )
}
