import { api } from '@/lib/api'
import type { Generation, RunToolPayload, StudioTool } from './types'

export async function listTools(): Promise<StudioTool[]> {
  const { data } = await api.get<{ data: StudioTool[] }>('/tools')
  return data.data
}

export async function runTool(tool: string, payload: RunToolPayload): Promise<Generation> {
  const { data } = await api.post<{ data: Generation }>(`/tools/${tool}/run`, payload)
  return data.data
}

export async function getGeneration(id: string): Promise<Generation> {
  const { data } = await api.get<{ data: Generation }>(`/generations/${id}`)
  return data.data
}

export async function listGenerations(params: { tool?: string; limit?: number } = {}): Promise<Generation[]> {
  const { data } = await api.get<{ data: Generation[] }>('/generations', { params })
  return data.data
}
