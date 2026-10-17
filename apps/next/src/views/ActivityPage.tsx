import { Link } from '@/lib/router'
import { Activity } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useActivity } from '@/features/workflows/hooks'
import { buildTimeline, formatTime, type TimelineTone } from '@/features/workflows/timeline'
import type { Workflow } from '@/features/workflows/types'
import { cn } from '@/lib/utils'

const TONE_TEXT: Record<TimelineTone, string> = {
  info: 'text-cyan-400',
  success: 'text-emerald-400',
  warning: 'text-lime-300',
  danger: 'text-red-400',
}

function WorkflowActivityCard({ workflow }: { workflow: Workflow }) {
  const events = buildTimeline(workflow).slice(0, 8)
  const title = workflow.project?.title ?? 'Untitled production'

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="min-w-0">
            <Link
              to={`/app/projects/${workflow.project_id}`}
              className="truncate font-medium hover:text-brand"
            >
              {title}
            </Link>
            <p className="text-xs text-muted-foreground">
              {workflow.status_label} · {workflow.progress?.completed ?? 0} of {workflow.progress?.total ?? 0} stages
              · updated {formatTime(workflow.updated_at)}
            </p>
          </div>
          <Link to={`/app/projects/${workflow.project_id}`} className="text-xs text-muted-foreground hover:text-foreground">
            Open production →
          </Link>
        </div>

        {events.length === 0 ? (
          <p className="text-xs text-muted-foreground">No activity yet.</p>
        ) : (
          <ol className="space-y-2">
            {events.map((event) => (
              <li key={event.id} className="flex items-center gap-3 text-sm">
                <span className={cn('w-16 shrink-0 text-xs', TONE_TEXT[event.tone])}>
                  {event.tone === 'success' ? 'Done' : event.tone === 'danger' ? 'Failed' : event.tone === 'warning' ? 'Waiting' : 'Update'}
                </span>
                <span className="flex-1">{event.label}</span>
                <time className="text-xs text-muted-foreground" dateTime={event.at}>
                  {formatTime(event.at)}
                </time>
              </li>
            ))}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}

export function ActivityPage() {
  const { data, isLoading } = useActivity()
  const workflows = data ?? []

  return (
    <div>
      <PageHeader
        title="Activity"
        description="Every stage that ran or is waiting for you, across all your productions."
      />

      {isLoading ? (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-40 rounded-xl" />
          ))}
        </div>
      ) : workflows.length === 0 ? (
        <Card className="glass border-dashed border-border/60">
          <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
            <div className="grid size-12 place-items-center rounded-full bg-brand/10 text-brand">
              <Activity className="size-5" />
            </div>
            <div>
              <p className="font-medium">No activity yet</p>
              <p className="text-sm text-muted-foreground">Start a production and its stages will appear here.</p>
            </div>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {workflows.map((workflow) => (
            <WorkflowActivityCard key={workflow.id} workflow={workflow} />
          ))}
        </div>
      )}
    </div>
  )
}
