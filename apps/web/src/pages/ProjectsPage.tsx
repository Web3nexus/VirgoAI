import { useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, FolderKanban } from 'lucide-react'
import { StatusBadge } from '@/components/projects/StatusBadge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { CreateProjectDialog } from '@/features/projects/CreateProjectDialog'
import { useProjects } from '@/features/projects/hooks'
import { PROJECT_TYPE_LABELS, type Project, type ProjectStatus } from '@/features/projects/types'
import { cn } from '@/lib/utils'

const FILTERS: { value: ProjectStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'draft', label: 'Drafts' },
  { value: 'active', label: 'Active' },
  { value: 'completed', label: 'Completed' },
  { value: 'archived', label: 'Archived' },
]

/**
 * Projects as a gallery: each one is a large thumbnail with its title, like a directors' project board.
 */
export function ProjectsPage() {
  const [status, setStatus] = useState<ProjectStatus | 'all'>('all')
  const { data, isLoading } = useProjects({ status })
  const projects = data?.data ?? []

  return (
    <div className="mx-auto w-full max-w-7xl">
      <header className="flex flex-wrap items-center gap-4">
        <Link
          to="/"
          aria-label="Back to home"
          className="grid size-8 place-items-center rounded-full text-muted-foreground transition-colors hover:text-foreground"
        >
          <ArrowLeft className="size-4" />
        </Link>
        <h1 className="font-heading text-xl font-semibold tracking-tight">Projects</h1>
        <CreateProjectDialog label="Create New" triggerClassName="rounded-full bg-foreground text-background hover:bg-foreground/90" />
      </header>

      <div role="tablist" aria-label="Project status" className="mt-6 flex flex-wrap gap-2">
        {FILTERS.map((filter) => (
          <button
            key={filter.value}
            type="button"
            role="tab"
            aria-selected={status === filter.value}
            onClick={() => setStatus(filter.value)}
            className={cn(
              'rounded-full px-4 py-1.5 text-sm transition-colors',
              status === filter.value ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground',
            )}
          >
            {filter.label}
          </button>
        ))}
      </div>

      <div className="mt-8">
        {isLoading ? (
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} className="aspect-video rounded-2xl" />
            ))}
          </div>
        ) : projects.length === 0 ? (
          <Card className="glass border-dashed border-border/60">
            <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
              <div className="grid size-12 place-items-center rounded-full bg-brand/10 text-brand">
                <FolderKanban className="size-5" />
              </div>
              <div>
                <p className="font-medium">No projects here</p>
                <p className="text-sm text-muted-foreground">
                  {status === 'all' ? 'Create your first project to get started.' : 'No projects match this filter.'}
                </p>
              </div>
              {status === 'all' && <CreateProjectDialog label="Create New" />}
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 xl:grid-cols-4">
            {projects.map((project) => (
              <ProjectTile key={project.id} project={project} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

function ProjectTile({ project }: { project: Project }) {
  return (
    <Link to={`/projects/${project.id}`} className="group flex flex-col gap-3">
      <div className="relative aspect-video overflow-hidden rounded-2xl border border-border bg-card/60">
        {project.preview_url ? (
          <img src={project.preview_url} alt="" className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]" loading="lazy" />
        ) : (
          <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand/20 via-card to-ink-900">
            <FolderKanban className="size-6 text-muted-foreground" />
          </div>
        )}
        <div className="absolute left-3 top-3">
          <StatusBadge status={project.status} />
        </div>
      </div>
      <div className="px-1">
        <p className="truncate font-medium group-hover:text-brand">{project.title}</p>
        <p className="mt-0.5 truncate text-xs text-muted-foreground">
          {PROJECT_TYPE_LABELS[project.type]} · {new Date(project.created_at).toLocaleDateString()}
        </p>
      </div>
    </Link>
  )
}
