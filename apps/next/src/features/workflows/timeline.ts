import type { Approval, Workflow, WorkflowTask } from './types'

export type TimelineTone = 'info' | 'success' | 'warning' | 'danger'

export type TimelineEvent = {
  id: string
  at: string
  label: string
  tone: TimelineTone
  stage?: string
}

const TASK_FINISHED: Record<string, { label: string; tone: TimelineTone }> = {
  completed: { label: 'completed', tone: 'success' },
  failed: { label: 'failed', tone: 'danger' },
  cancelled: { label: 'cancelled', tone: 'info' },
  skipped: { label: 'skipped', tone: 'info' },
}

const APPROVAL_RESOLVED: Record<string, { label: string; tone: TimelineTone }> = {
  approved: { label: 'approved', tone: 'success' },
  revision_requested: { label: 'revised', tone: 'info' },
  regenerated: { label: 'regenerated', tone: 'info' },
  rejected: { label: 'rejected', tone: 'danger' },
}

function taskEvents(task: WorkflowTask): TimelineEvent[] {
  const events: TimelineEvent[] = []
  if (task.started_at) {
    events.push({
      id: `${task.id}-start`,
      at: task.started_at,
      label: `${task.name} started`,
      tone: 'info',
      stage: task.key,
    })
  }
  if (task.finished_at) {
    const finished = TASK_FINISHED[task.status]
    if (finished) {
      events.push({
        id: `${task.id}-finish`,
        at: task.finished_at,
        label: `${task.name} ${finished.label}`,
        tone: finished.tone,
        stage: task.key,
      })
    }
  }
  return events
}

function approvalEvents(approval: Approval, stage: string | undefined, stageName: string | undefined): TimelineEvent[] {
  const events: TimelineEvent[] = []
  const requestedAt = approval.requested_at ?? approval.created_at
  events.push({
    id: `${approval.id}-requested`,
    at: requestedAt,
    label: `Waiting for your approval: ${stageName ?? 'checkpoint'}`,
    tone: 'warning',
    stage,
  })
  if (approval.resolved_at) {
    const resolved = APPROVAL_RESOLVED[approval.status]
    if (resolved) {
      events.push({
        id: `${approval.id}-resolved`,
        at: approval.resolved_at,
        label: `${stageName ?? 'Checkpoint'} ${resolved.label}`,
        tone: resolved.tone,
        stage,
      })
    }
  }
  return events
}

/** Newest first. Built from task timestamps and approval decisions, so it needs no extra endpoint. */
export function buildTimeline(workflow: Workflow): TimelineEvent[] {
  const taskById = new Map(workflow.tasks.map((task) => [task.id, task]))
  const events = [
    ...workflow.tasks.flatMap(taskEvents),
    ...workflow.approvals.flatMap((approval) => {
      const task = taskById.get(approval.workflow_task_id)
      return approvalEvents(approval, task?.key, task?.name)
    }),
  ]
  return events.sort((a, b) => Date.parse(b.at) - Date.parse(a.at))
}

export function formatTime(iso: string | null | undefined): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(new Date(iso))
}

export function formatDuration(start: string | null, end: string | null): string {
  if (!start || !end) return '—'
  const seconds = Math.max(0, Math.round((Date.parse(end) - Date.parse(start)) / 1000))
  if (seconds < 60) return `${seconds}s`
  return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
}

export function isPlaceholder(task: WorkflowTask): boolean {
  return task.output?.placeholder === true
}
