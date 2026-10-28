import { api } from '@/lib/api'
import type { Workflow, WorkflowMode } from './types'

export type StartWorkflowPayload = {
  template_key?: string
  mode?: WorkflowMode
}

export async function getProjectWorkflow(projectId: string): Promise<Workflow> {
  const { data } = await api.get<{ data: Workflow }>(`/projects/${projectId}/workflow`)
  return data.data
}

export async function startWorkflow(
  projectId: string,
  payload: StartWorkflowPayload = {},
): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/projects/${projectId}/start`, payload)
  return data.data
}

export async function pauseWorkflow(workflowId: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/workflows/${workflowId}/pause`)
  return data.data
}

export async function resumeWorkflow(workflowId: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/workflows/${workflowId}/resume`)
  return data.data
}

export async function cancelWorkflow(workflowId: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/workflows/${workflowId}/cancel`)
  return data.data
}

export async function approveApproval(id: string, notes?: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/approvals/${id}/approve`, { notes })
  return data.data
}

export async function reviseApproval(
  id: string,
  revision: Record<string, unknown>,
  notes?: string,
): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/approvals/${id}/revise`, {
    revision,
    notes,
  })
  return data.data
}

export async function regenerateApproval(id: string, notes?: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/approvals/${id}/regenerate`, { notes })
  return data.data
}

export async function rejectApproval(id: string, notes?: string): Promise<Workflow> {
  const { data } = await api.post<{ data: Workflow }>(`/approvals/${id}/reject`, { notes })
  return data.data
}

export async function updateTaskPrompt(
  workflowId: string,
  taskId: string,
  prompt: string,
): Promise<Workflow> {
  const { data } = await api.patch<{ data: Workflow }>(`/workflows/${workflowId}/tasks/${taskId}`, { prompt })
  return data.data
}

export async function getActivity(limit = 30): Promise<Workflow[]> {
  const { data } = await api.get<{ data: Workflow[] }>('/activity', { params: { limit } })
  return data.data
}
