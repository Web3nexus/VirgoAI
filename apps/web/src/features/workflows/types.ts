export const WORKFLOW_MODES = ['guided', 'assisted', 'full_handoff'] as const
export type WorkflowMode = (typeof WORKFLOW_MODES)[number]

export const WORKFLOW_MODE_LABELS: Record<WorkflowMode, string> = {
  guided: 'Guided',
  assisted: 'Assisted',
  full_handoff: 'Full handoff',
}

export type WorkflowStatus =
  | 'draft'
  | 'queued'
  | 'running'
  | 'awaiting_approval'
  | 'awaiting_revision'
  | 'paused'
  | 'completed'
  | 'failed'
  | 'cancelled'

export type TaskStatus =
  | 'pending'
  | 'queued'
  | 'running'
  | 'awaiting_approval'
  | 'completed'
  | 'failed'
  | 'skipped'
  | 'cancelled'

export type ApprovalStatus =
  | 'pending'
  | 'approved'
  | 'revision_requested'
  | 'regenerated'
  | 'rejected'

export type WorkflowTask = {
  id: string
  key: string
  stage: string
  name: string
  status: TaskStatus
  status_label: string
  position: number
  approval_policy: string
  requires_approval: boolean
  attempts: number
  output: Record<string, unknown> | null
  error: string | null
  started_at: string | null
  finished_at: string | null
}

export type Approval = {
  id: string
  workflow_id: string
  workflow_task_id: string
  status: ApprovalStatus
  policy: string
  summary: string | null
  payload: Record<string, unknown> | null
  requested_at: string | null
  resolved_at: string | null
  created_at: string
}

export type WorkflowProgress = {
  total: number
  completed: number
  percent: number
}

export type Workflow = {
  id: string
  project_id: string
  project?: { id: string; title: string }
  template_key: string
  mode: WorkflowMode
  mode_label: string
  status: WorkflowStatus
  status_label: string
  current_stage: string | null
  started_at: string | null
  finished_at: string | null
  paused_at: string | null
  created_at: string
  updated_at: string
  progress: WorkflowProgress | null
  tasks: WorkflowTask[]
  approvals: Approval[]
}

export const ACTIVE_WORKFLOW_STATUSES: WorkflowStatus[] = [
  'queued',
  'running',
  'awaiting_approval',
  'awaiting_revision',
  'paused',
]

export function isWorkflowActive(status: WorkflowStatus): boolean {
  return ACTIVE_WORKFLOW_STATUSES.includes(status)
}
