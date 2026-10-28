import { useMemo, useState } from 'react'
import {
  Background,
  Controls,
  Handle,
  Position,
  ReactFlow,
  type Edge,
  type Node,
  type NodeMouseHandler,
  type NodeProps,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import { AlertTriangle, Ban, CheckCircle2, CircleDashed, Loader2, XCircle } from 'lucide-react'
import { OutputView } from './OutputView'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { GRAPH_NODE_HEIGHT, GRAPH_NODE_WIDTH, isLiveTask, layoutTasks } from '@/features/workflows/graph'
import { useUpdateTaskPrompt } from '@/features/workflows/hooks'
import { formatDuration, isPlaceholder } from '@/features/workflows/timeline'
import type { TaskStatus, Workflow, WorkflowTask } from '@/features/workflows/types'

type TaskNodeData = { task: WorkflowTask; placeholder: boolean }
type TaskNodeType = Node<TaskNodeData, 'task'>

const STATUS_BORDER: Record<TaskStatus, string> = {
  completed: 'border-emerald-400/40',
  running: 'border-cyan-400/60',
  queued: 'border-cyan-400/40',
  awaiting_approval: 'border-lime-300/60',
  pending: 'border-border/60',
  failed: 'border-red-400/60',
  skipped: 'border-border/40',
  cancelled: 'border-border/40',
}

const STATUS_TEXT: Record<TaskStatus, string> = {
  completed: 'text-emerald-400',
  running: 'text-cyan-400',
  queued: 'text-cyan-400',
  awaiting_approval: 'text-lime-300',
  pending: 'text-muted-foreground/60',
  failed: 'text-red-400',
  skipped: 'text-muted-foreground/60',
  cancelled: 'text-muted-foreground/60',
}

function StatusIcon({ status }: { status: TaskStatus }) {
  const className = cn('size-4 shrink-0', STATUS_TEXT[status])
  switch (status) {
    case 'completed':
      return <CheckCircle2 className={className} />
    case 'running':
    case 'queued':
      return <Loader2 className={cn(className, status === 'running' && 'animate-spin')} />
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

function TaskNode({ data, selected }: NodeProps<TaskNodeType>) {
  const { task, placeholder } = data

  return (
    <div
      className={cn(
        'flex h-full flex-col justify-between rounded-xl border bg-ink-900 px-3.5 py-2.5 text-left shadow-sm transition-colors',
        STATUS_BORDER[task.status],
        selected && 'ring-2 ring-brand/60',
      )}
      style={{ width: GRAPH_NODE_WIDTH, height: GRAPH_NODE_HEIGHT }}
    >
      <Handle type="target" position={Position.Left} className="!size-2 !border-0 !bg-border" />
      <div className="flex items-start gap-2">
        <StatusIcon status={task.status} />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{task.name}</p>
          <p className={cn('text-xs capitalize', STATUS_TEXT[task.status])}>{task.status_label}</p>
        </div>
      </div>
      <div className="flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
        {placeholder ? (
          <span className="rounded-full border border-amber-400/30 bg-amber-400/10 px-2 py-px uppercase tracking-wide text-amber-300">
            Placeholder
          </span>
        ) : (
          <span>{task.requires_approval ? 'Needs approval' : 'Automatic'}</span>
        )}
        <span>{formatDuration(task.started_at, task.finished_at)}</span>
      </div>
      <Handle type="source" position={Position.Right} className="!size-2 !border-0 !bg-border" />
    </div>
  )
}

const NODE_TYPES = { task: TaskNode }

function StageDetail({ projectId, workflowId, task }: { projectId: string; workflowId: string; task: WorkflowTask }) {
  const save = useUpdateTaskPrompt(projectId)
  const [draft, setDraft] = useState(task.prompt ?? '')
  const dirty = draft.trim() !== (task.prompt ?? '')

  return (
    <div className="space-y-3 rounded-xl border border-border/60 p-4 text-sm">
      <div className="flex flex-wrap items-center gap-2">
        <StatusIcon status={task.status} />
        <span className="font-medium">{task.name}</span>
        <span className="text-xs capitalize text-muted-foreground">{task.status_label}</span>
      </div>
      {task.error && <p className="text-xs text-red-400">{task.error}</p>}

      {task.prompt_editable ? (
        <div className="space-y-2">
          <label htmlFor={`stage-prompt-${task.id}`} className="text-xs uppercase tracking-wide text-muted-foreground">
            Instructions for this run
          </label>
          <Textarea
            id={`stage-prompt-${task.id}`}
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            rows={3}
            maxLength={4000}
            placeholder="Leave empty to use the template's instructions."
          />
          {dirty && (
            <div className="flex gap-2">
              <Button
                size="sm"
                disabled={save.isPending}
                onClick={() => save.mutate({ workflowId, taskId: task.id, prompt: draft.trim() })}
              >
                Save instructions
              </Button>
              <Button size="sm" variant="ghost" onClick={() => setDraft(task.prompt ?? '')}>
                Discard
              </Button>
            </div>
          )}
        </div>
      ) : (
        task.prompt && <p className="text-xs text-muted-foreground">Instructions: {task.prompt}</p>
      )}

      <OutputView output={task.output} />
    </div>
  )
}

export function WorkflowGraph({ projectId, workflow }: { projectId: string; workflow: Workflow }) {
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const { nodes, edges } = useMemo(() => {
    const positions = layoutTasks(workflow)
    const taskById = new Map(workflow.tasks.map((task) => [task.id, task]))

    const nodes: TaskNodeType[] = workflow.tasks.map((task) => ({
      id: task.id,
      type: 'task',
      position: positions.get(task.id) ?? { x: 0, y: 0 },
      data: { task, placeholder: isPlaceholder(task) },
      selected: task.id === selectedId,
      draggable: false,
      selectable: true,
    }))

    const edges: Edge[] = workflow.dependencies.map((dependency) => {
      const target = taskById.get(dependency.task_id)
      const live = target ? isLiveTask(target) : false
      return {
        id: `${dependency.depends_on_task_id}->${dependency.task_id}`,
        source: dependency.depends_on_task_id,
        target: dependency.task_id,
        animated: live,
        style: { stroke: live ? 'var(--color-cyan-400)' : 'var(--color-border)', strokeWidth: 1.5 },
      }
    })

    return { nodes, edges }
  }, [workflow, selectedId])

  const selectedTask = workflow.tasks.find((task) => task.id === selectedId) ?? null

  const handleNodeClick: NodeMouseHandler<TaskNodeType> = (_, node) => {
    setSelectedId((current) => (current === node.id ? null : node.id))
  }

  return (
    <div className="space-y-3">
      <div
        role="img"
        aria-label={`Workflow graph with ${workflow.tasks.length} stages`}
        className="h-[360px] overflow-hidden rounded-xl border border-border/60 bg-ink-2 sm:h-[460px]"
      >
        <ReactFlow
          nodes={nodes}
          edges={edges}
          nodeTypes={NODE_TYPES}
          onNodeClick={handleNodeClick}
          onPaneClick={() => setSelectedId(null)}
          nodesDraggable={false}
          nodesConnectable={false}
          elementsSelectable
          fitView
          fitViewOptions={{ padding: 0.2 }}
          minZoom={0.3}
          maxZoom={1.5}
          colorMode="dark"
          proOptions={{ hideAttribution: true }}
        >
          <Background gap={24} size={1} />
          <Controls showInteractive={false} />
        </ReactFlow>
      </div>

      {selectedTask && (
        <StageDetail key={selectedTask.id} projectId={projectId} workflowId={workflow.id} task={selectedTask} />
      )}
    </div>
  )
}
