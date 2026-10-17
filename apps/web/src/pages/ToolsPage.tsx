import { Link } from 'react-router-dom'
import { AlertCircle, ArrowRight, CheckCircle2 } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useTools } from '@/features/tools/hooks'
import type { StudioTool } from '@/features/tools/types'

function ToolStatus({ tool }: { tool: StudioTool }) {
  if (tool.ready) {
    const usable = tool.models.find((model) => model.usable)
    return (
      <p className="flex items-center gap-1.5 text-xs text-emerald-400">
        <CheckCircle2 className="size-3.5" />
        {usable?.provider_key === 'echo' ? 'Ready · test model only' : `Ready · ${usable?.name}`}
      </p>
    )
  }
  const reason = tool.models[0]?.reason ?? 'No model is registered for this tool yet.'
  return (
    <p className="flex items-start gap-1.5 text-xs text-amber-300">
      <AlertCircle className="mt-px size-3.5 shrink-0" /> {reason}
    </p>
  )
}

export function ToolsPage() {
  const { data: tools, isLoading } = useTools()

  return (
    <div>
      <PageHeader
        title="Tools"
        description="Run one step by hand. Pick the model, write a prompt, and keep what you like."
      />

      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-40 rounded-xl" />
          ))}
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(tools ?? []).map((tool) => (
            <Link key={tool.key} to={`/tools/${tool.key}`} className="group">
              <Card className="glass h-full border-border/60 transition-colors group-hover:border-brand/40">
                <CardContent className="flex h-full flex-col gap-3 p-5">
                  <div className="flex items-center justify-between">
                    <p className="font-medium">{tool.label}</p>
                    <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-brand" />
                  </div>
                  <p className="text-sm text-muted-foreground">{tool.description}</p>
                  <div className="mt-auto pt-2">
                    <ToolStatus tool={tool} />
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
