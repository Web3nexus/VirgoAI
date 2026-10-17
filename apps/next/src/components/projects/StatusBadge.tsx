import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { ProjectStatus } from '@/features/projects/types'

const STATUS_STYLES: Record<ProjectStatus, string> = {
  draft: 'bg-muted text-muted-foreground',
  active: 'bg-brand/15 text-brand',
  completed: 'bg-neon-emerald/15 text-neon-emerald',
  archived: 'bg-input/20 text-muted-foreground',
}

export function StatusBadge({ status }: { status: ProjectStatus }) {
  return (
    <Badge variant="secondary" className={cn('capitalize', STATUS_STYLES[status])}>
      {status}
    </Badge>
  )
}
