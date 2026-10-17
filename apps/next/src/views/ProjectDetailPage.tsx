import { Link, useNavigate, useParams } from '@/lib/router'
import { ArrowLeft, Loader2, Trash2 } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { StatusBadge } from '@/components/projects/StatusBadge'
import { WorkflowPanel } from '@/components/workflows/WorkflowPanel'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useDeleteProject, useProject, useUpdateProject } from '@/features/projects/hooks'
import { PROJECT_STATUSES, PROJECT_TYPE_LABELS, type ProjectStatus } from '@/features/projects/types'

export function ProjectDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: project, isLoading, isError } = useProject(id)
  const updateProject = useUpdateProject(id ?? '')
  const deleteProject = useDeleteProject()

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-9 w-64" />
        <Skeleton className="h-48 w-full rounded-xl" />
      </div>
    )
  }

  if (isError || !project) {
    return (
      <div className="space-y-4">
        <p className="text-muted-foreground">Project not found.</p>
        <Button variant="outline" nativeButton={false} render={<Link to="/app/projects" />}>
          Back to projects
        </Button>
      </div>
    )
  }

  return (
    <div>
      <Button
        variant="ghost"
        size="sm"
        className="mb-4 -ml-2 gap-1 text-muted-foreground"
        onClick={() => navigate('/app/projects')}
      >
        <ArrowLeft className="size-4" />
        Projects
      </Button>

      <PageHeader
        title={project.title}
        description={PROJECT_TYPE_LABELS[project.type]}
        action={
          <div className="flex items-center gap-3">
            <StatusBadge status={project.status} />
            <AlertDialog>
              <AlertDialogTrigger
                render={<Button variant="outline" size="icon" className="text-destructive" />}
              >
                <Trash2 className="size-4" />
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Delete this project?</AlertDialogTitle>
                  <AlertDialogDescription>
                    This soft-deletes “{project.title}”. This action can be reverted by an admin.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancel</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={() =>
                      deleteProject.mutate(project.id, { onSuccess: () => navigate('/app/projects') })
                    }
                  >
                    Delete
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          </div>
        }
      />

      <div className="space-y-6">
        <Card className="glass border-border/60">
          <CardContent className="space-y-4 p-6">
            <div>
              <h2 className="text-sm font-medium text-muted-foreground">Description</h2>
              <p className="mt-1.5 text-sm">{project.description || 'No description yet.'}</p>
            </div>

            <div className="flex items-center gap-3">
              <div className="space-y-2">
                <h2 className="text-sm font-medium text-muted-foreground">Status</h2>
                <Select
                  value={project.status}
                  onValueChange={(value: string | null) =>
                    updateProject.mutate({ status: value as ProjectStatus })
                  }
                >
                  <SelectTrigger className="w-44">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {PROJECT_STATUSES.map((status) => (
                      <SelectItem key={status} value={status} className="capitalize">
                        {status}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              {updateProject.isPending && (
                <Loader2 className="mt-6 size-4 animate-spin text-muted-foreground" />
              )}
            </div>

            <div className="grid grid-cols-2 gap-4 border-t border-border/60 pt-4 text-xs text-muted-foreground">
              <div>
                <span className="block">Created</span>
                <span className="text-foreground">
                  {new Date(project.created_at).toLocaleString()}
                </span>
              </div>
              <div>
                <span className="block">Updated</span>
                <span className="text-foreground">
                  {new Date(project.updated_at).toLocaleString()}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <WorkflowPanel projectId={project.id} />
      </div>
    </div>
  )
}
