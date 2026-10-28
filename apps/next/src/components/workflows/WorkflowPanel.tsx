import { useState } from 'react'
import { OutputView } from './OutputView'
import { WorkflowGraph } from './WorkflowGraph'
import {
  AlertTriangle,
  Ban,
  CheckCircle2,
  CircleDashed,
  Loader2,
  Pause,
  Play,
  RefreshCw,
  Sparkles,
  XCircle,
} from 'lucide-react'
import { ChevronDown } from 'lucide-react'
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
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { useApprovalDecision, useProjectWorkflow, useStartWorkflow, useWorkflowAction } from '@/features/workflows/hooks'
import {
  WORKFLOW_MODES,
  WORKFLOW_MODE_LABELS,
  type Approval,
  type TaskStatus,
  type Workflow,
  type WorkflowMode,
  type WorkflowTask,
} from '@/features/workflows/types'
import {
  buildTimeline,
  formatDuration,
  formatTime,
  isPlaceholder,
  type TimelineTone,
} from '@/features/workflows/timeline'

const GENERATIVE_VIDEO_TEMPLATE = 'generative_video'

const MODE_HELP: Record<WorkflowMode, string> = {
  guided: 'Approve every stage before the next one runs.',
  assisted: 'Approve only the storyboard and the final review. Everything else runs on its own.',
  full_handoff: 'No approvals. The pipeline runs to the end, and you review the finished result.',
}

const TASK_STATUS_STYLES: Record<TaskStatus, string> = {
  completed: 'text-emerald-400',
  running: 'text-cyan-400',
  queued: 'text-cyan-400',
  awaiting_approval: 'text-lime-300',
  pending: 'text-muted-foreground/60',
  failed: 'text-red-400',
  skipped: 'text-muted-foreground/60',
  cancelled: 'text-muted-foreground/60',
}

function TaskStatusIcon({ status }: { status: TaskStatus }) {
  const className = cn('size-4 shrink-0', TASK_STATUS_STYLES[status])
  switch (status) {
    case 'completed':
      return <CheckCircle2 className={className} />
    case 'running':
      return <Loader2 className={cn(className, 'animate-spin')} />
    case 'queued':
      return <Loader2 className={className} />
    case 'awaiting_approval':
      return <AlertTriangle className={className} />
    case 'failed':
      return <XCircle className={className} />
    case 'cancelled':
      return <Ban className={className} />
    default:
      return <CircleDashed className={className} />
  }
}

function WorkflowStatusPill({ workflow }: { workflow: Workflow }) {
  const tone =
    workflow.status === 'completed'
      ? 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30'
      : workflow.status === 'failed'
        ? 'bg-red-400/15 text-red-300 border-red-400/30'
        : workflow.status === 'cancelled'
          ? 'bg-muted text-muted-foreground border-border'
          : 'bg-brand/15 text-brand border-brand/30'

  return (
    <span className={cn('rounded-full border px-2.5 py-0.5 text-xs font-medium', tone)}>
      {workflow.status_label}
    </span>
  )
}

function PendingApproval({ projectId, approval }: { projectId: string; approval: Approval }) {
  const decide = useApprovalDecision(projectId)
  const [editing, setEditing] = useState(false)
  const [summary, setSummary] = useState(String(approval.payload?.summary ?? ''))
  const busy = decide.isPending

  const run = (decision: 'approve' | 'regenerate' | 'revise' | 'reject', revision?: Record<string, unknown>) =>
    decide.mutate({ approvalId: approval.id, decision, revision })

  return (
    <div className="rounded-xl border border-lime-300/30 bg-lime-300/5 p-4">
      <div className="flex items-start gap-3">
        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-lime-300" />
        <div className="flex-1 space-y-3">
          <div>
            <p className="text-sm font-medium">Checkpoint: {approval.summary}</p>
            <p className="text-xs text-muted-foreground">
              Policy: {approval.policy.replace(/_/g, ' ')}
            </p>
          </div>

          {editing ? (
            <Textarea
              value={summary}
              onChange={(event) => setSummary(event.target.value)}
              rows={3}
              placeholder="Edit the stage output before continuing"
            />
          ) : (
            <div className="max-h-64 overflow-auto rounded-lg bg-ink-2 p-3 text-sm">
              <OutputView output={approval.payload} />
            </div>
          )}

          <div className="flex flex-wrap gap-2">
            {editing ? (
              <>
                <Button
                  size="sm"
                  disabled={busy}
                  onClick={() => run('revise', { summary })}
                >
                  Save &amp; continue
                </Button>
                <Button size="sm" variant="ghost" disabled={busy} onClick={() => setEditing(false)}>
                  Cancel
                </Button>
              </>
            ) : (
              <>
                <Button size="sm" disabled={busy} onClick={() => run('approve')}>
                  Approve &amp; continue
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() => setEditing(true)}
                >
                  Edit before continuing
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  className="gap-1.5"
                  disabled={busy}
                  onClick={() => run('regenerate')}
                >
                  <RefreshCw className={cn('size-3.5', busy && 'animate-spin')} />
                  Regenerate
                </Button>
                <Button
                  size="sm"
                  variant="ghost"
                  className="text-destructive"
                  disabled={busy}
                  onClick={() => run('reject')}
                >
                  Reject
                </Button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

function ActiveWorkflow({ projectId, workflow }: { projectId: string; workflow: Workflow }) {
  const pause = useWorkflowAction(projectId, 'pause')
  const resume = useWorkflowAction(projectId, 'resume')
  const cancel = useWorkflowAction(projectId, 'cancel')

  const [view, setView] = useState<'graph' | 'list'>('graph')
  const pendingApproval = workflow.approvals.find((a) => a.status === 'pending')
  const progress = workflow.progress
  const canPause = ['running', 'queued', 'awaiting_approval'].includes(workflow.status)
  const canResume = workflow.status === 'paused'
  const canCancel = !['completed', 'cancelled', 'failed'].includes(workflow.status)

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-5 p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <WorkflowStatusPill workflow={workflow} />
            <span className="text-xs text-muted-foreground">
              {workflow.mode_label} · {workflow.template_key}
            </span>
          </div>
          <div className="flex gap-2">
            {canPause && (
              <Button
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={pause.isPending}
                onClick={() => pause.mutate(workflow.id)}
              >
                <Pause className="size-3.5" /> Pause
              </Button>
            )}
            {canResume && (
              <Button
                size="sm"
                variant="outline"
                className="gap-1.5"
                disabled={resume.isPending}
                onClick={() => resume.mutate(workflow.id)}
              >
                <Play className="size-3.5" /> Resume
              </Button>
            )}
            {canCancel && (
              <Button
                size="sm"
                variant="ghost"
                className="text-destructive"
                disabled={cancel.isPending}
                onClick={() => cancel.mutate(workflow.id)}
              >
                Cancel
              </Button>
            )}
          </div>
        </div>

        {progress && (
          <div className="space-y-1.5">
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-ink-3">
              <div
                className="h-full rounded-full bg-brand transition-all duration-500"
                style={{ width: `${progress.percent}%` }}
              />
            </div>
            <p className="text-xs text-muted-foreground">
              {progress.completed} of {progress.total} stages complete
            </p>
          </div>
        )}

        <div className="flex w-fit gap-1 rounded-lg bg-ink-2 p-1" role="group" aria-label="Stage view">
          {(['graph', 'list'] as const).map((option) => (
            <Button
              key={option}
              size="sm"
              variant={view === option ? 'secondary' : 'ghost'}
              aria-pressed={view === option}
              onClick={() => setView(option)}
              className="capitalize"
            >
              {option}
            </Button>
          ))}
        </div>

        {view === 'graph' ? (
          <WorkflowGraph projectId={projectId} workflow={workflow} />
        ) : (
          <ul className="divide-y divide-border/50">
            {workflow.tasks.map((task) => (
              <TaskRow key={task.id} task={task} />
            ))}
          </ul>
        )}

        {pendingApproval && <PendingApproval projectId={projectId} approval={pendingApproval} />}

        <ActivityLog workflow={workflow} />
      </CardContent>
    </Card>
  )
}

function TaskRow({ task }: { task: WorkflowTask }) {
  const [open, setOpen] = useState(false)
  const placeholder = isPlaceholder(task)

  return (
    <li className="py-2.5">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        className="flex w-full items-center gap-3 text-left"
      >
        <TaskStatusIcon status={task.status} />
        <span className="text-sm font-medium">{task.name}</span>
        <span className="text-xs capitalize text-muted-foreground">{task.status_label}</span>
        {placeholder && (
          <span className="rounded-full border border-amber-400/30 bg-amber-400/10 px-2 py-px text-[10px] uppercase tracking-wide text-amber-300">
            Placeholder
          </span>
        )}
        <span className="ml-auto text-xs text-muted-foreground">
          {formatDuration(task.started_at, task.finished_at)}
        </span>
        <ChevronDown className={cn('size-4 text-muted-foreground transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div className="mt-3 space-y-3 pl-7 text-xs">
          <dl className="grid grid-cols-2 gap-x-6 gap-y-1.5 text-muted-foreground sm:grid-cols-4">
            <div>
              <dt className="uppercase tracking-wide text-[10px]">Attempts</dt>
              <dd className="text-foreground">{task.attempts}</dd>
            </div>
            <div>
              <dt className="uppercase tracking-wide text-[10px]">Started</dt>
              <dd className="text-foreground">{formatTime(task.started_at)}</dd>
            </div>
            <div>
              <dt className="uppercase tracking-wide text-[10px]">Finished</dt>
              <dd className="text-foreground">{formatTime(task.finished_at)}</dd>
            </div>
            <div>
              <dt className="uppercase tracking-wide text-[10px]">Approval</dt>
              <dd className="text-foreground">{task.requires_approval ? 'Required' : 'Automatic'}</dd>
            </div>
          </dl>

          {task.error && <p className="text-red-400">{task.error}</p>}

          <OutputView output={task.output} />
        </div>
      )}
    </li>
  )
}

const TONE_CLASS: Record<TimelineTone, string> = {
  info: 'bg-cyan-400',
  success: 'bg-emerald-400',
  warning: 'bg-lime-300',
  danger: 'bg-red-400',
}

function ActivityLog({ workflow }: { workflow: Workflow }) {
  const events = buildTimeline(workflow)

  return (
    <section aria-label="Activity" className="space-y-3 border-t border-border/50 pt-5">
      <h3 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Activity</h3>
      {events.length === 0 ? (
        <p className="text-xs text-muted-foreground">Nothing has happened yet.</p>
      ) : (
        <ol className="space-y-2.5">
          {events.map((event) => (
            <li key={event.id} className="flex items-center gap-3 text-sm">
              <span className={cn('size-2 shrink-0 rounded-full', TONE_CLASS[event.tone])} aria-hidden />
              <span className="flex-1">{event.label}</span>
              <time className="text-xs text-muted-foreground" dateTime={event.at}>
                {formatTime(event.at)}
              </time>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}

function StartWorkflow({ projectId }: { projectId: string }) {
  const start = useStartWorkflow(projectId)
  const [mode, setMode] = useState<WorkflowMode>('guided')

  return (
    <Card className="glass border-brand/20">
      <CardContent className="flex flex-col items-center gap-3 p-6 text-center">
        <div className="grid size-12 place-items-center rounded-full bg-brand/10 text-brand">
          <Sparkles className="size-5" />
        </div>
        <div>
          <p className="font-medium">Generate content package</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Story → script → characters → environments → scenes → keyframes → motion → narration →
            edit → review → thumbnail. Choose how much you want to approve.
          </p>
        </div>
        <Select value={mode} onValueChange={(value: string | null) => setMode(value as WorkflowMode)}>
          <SelectTrigger className="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {WORKFLOW_MODES.map((option) => (
              <SelectItem key={option} value={option}>
                {WORKFLOW_MODE_LABELS[option]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className="w-full text-left text-xs text-muted-foreground">{MODE_HELP[mode]}</p>
        <Button
          className="w-full gap-2"
          disabled={start.isPending}
          onClick={() => start.mutate({ mode, template_key: GENERATIVE_VIDEO_TEMPLATE })}
        >
          {start.isPending ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
          Start pipeline
        </Button>
      </CardContent>
    </Card>
  )
}

export function WorkflowPanel({ projectId }: { projectId: string }) {
  const { data: workflow, isLoading } = useProjectWorkflow(projectId)

  if (isLoading) {
    return <Skeleton className="h-56 w-full rounded-xl" />
  }

  if (!workflow) {
    return <StartWorkflow projectId={projectId} />
  }

  return <ActiveWorkflow projectId={projectId} workflow={workflow} />
}
