import { useState } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'
import { GenerationOutput } from '@/components/tools/GenerationOutput'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useGenerations, useTools } from '@/features/tools/hooks'
import { formatTime } from '@/features/workflows/timeline'
import { cn } from '@/lib/utils'

export function LibraryPage() {
  const [tool, setTool] = useState<string | undefined>(undefined)
  const { data: tools } = useTools()
  const { data: items, isLoading } = useGenerations(tool, 60)

  return (
    <div>
      <PageHeader title="Library" description="Everything you have generated, newest first." />

      <div className="mb-6 flex flex-wrap gap-2">
        {[{ key: undefined, label: 'All' }, ...(tools ?? []).map((item) => ({ key: item.key, label: item.label }))].map(
          (option) => (
            <button
              key={option.label}
              type="button"
              onClick={() => setTool(option.key)}
              className={cn(
                'rounded-full border px-3 py-1 text-xs transition-colors',
                tool === option.key
                  ? 'border-brand/60 bg-brand/15 text-foreground'
                  : 'border-border text-muted-foreground hover:text-foreground',
              )}
            >
              {option.label}
            </button>
          ),
        )}
      </div>

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-48 rounded-xl" />
          ))}
        </div>
      ) : (items ?? []).length === 0 ? (
        <p className="text-sm text-muted-foreground">Nothing generated yet. Run a tool and it will show up here.</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(items ?? []).map((item) => (
            <Card key={item.id} className="glass h-80 border-border/60">
              <CardContent className="flex h-full flex-col gap-3 overflow-hidden p-4">
                <div className="h-44 shrink-0 overflow-hidden rounded-lg bg-ink-2">
                  <GenerationOutput generation={item} compact className="h-full" />
                </div>
                <p className="line-clamp-1 shrink-0 text-sm font-medium">{item.prompt}</p>
                <p className="mt-auto shrink-0 text-xs text-muted-foreground">
                  {tools?.find((t) => t.key === item.tool)?.label ?? item.tool} · {item.model_key ?? 'No model'} ·{' '}
                  {formatTime(item.created_at)}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
