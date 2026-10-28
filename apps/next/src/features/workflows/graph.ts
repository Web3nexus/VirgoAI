import type { Workflow, WorkflowTask } from './types'

export const GRAPH_NODE_WIDTH = 220
export const GRAPH_NODE_HEIGHT = 84
const COLUMN_GAP = 88
const ROW_GAP = 28

export type GraphPosition = { x: number; y: number }

/**
 * Places each task in a column by its longest chain of dependencies, so a stage always sits to
 * the right of everything it waits for. Tasks in the same column stack in pipeline order.
 */
export function layoutTasks(workflow: Workflow): Map<string, GraphPosition> {
  const parents = new Map<string, string[]>()
  for (const edge of workflow.dependencies) {
    parents.set(edge.task_id, [...(parents.get(edge.task_id) ?? []), edge.depends_on_task_id])
  }

  const depth = new Map<string, number>()
  const depthOf = (id: string, visiting: Set<string>): number => {
    const known = depth.get(id)
    if (known !== undefined) return known
    // The engine builds acyclic graphs. Guard anyway so a bad row cannot hang the page.
    if (visiting.has(id)) return 0
    visiting.add(id)
    const value = Math.max(-1, ...(parents.get(id) ?? []).map((parent) => depthOf(parent, visiting))) + 1
    visiting.delete(id)
    depth.set(id, value)
    return value
  }

  const ordered = [...workflow.tasks].sort((a, b) => a.position - b.position)
  const rowInColumn = new Map<number, number>()
  const positions = new Map<string, GraphPosition>()

  for (const task of ordered) {
    const column = depthOf(task.id, new Set())
    const row = rowInColumn.get(column) ?? 0
    rowInColumn.set(column, row + 1)
    positions.set(task.id, {
      x: column * (GRAPH_NODE_WIDTH + COLUMN_GAP),
      y: row * (GRAPH_NODE_HEIGHT + ROW_GAP),
    })
  }

  return positions
}

export function isLiveTask(task: WorkflowTask): boolean {
  return task.status === 'running' || task.status === 'queued'
}
