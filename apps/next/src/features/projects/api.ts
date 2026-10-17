import { api } from '@/lib/api'
import type { Paginated, Project, ProjectFormat, ProjectStatus, ProjectType } from './types'

export type ProjectListParams = {
  page?: number
  status?: ProjectStatus | 'all'
  type?: ProjectType | 'all'
  search?: string
}

export type ProjectPayload = {
  title: string
  description?: string | null
  type: ProjectType
  format?: ProjectFormat
}

export async function listProjects(params: ProjectListParams = {}): Promise<Paginated<Project>> {
  const { data } = await api.get<Paginated<Project>>('/projects', {
    params: {
      page: params.page,
      status: params.status && params.status !== 'all' ? params.status : undefined,
      type: params.type && params.type !== 'all' ? params.type : undefined,
      search: params.search || undefined,
    },
  })
  return data
}

export async function getProject(id: string): Promise<Project> {
  const { data } = await api.get<{ data: Project }>(`/projects/${id}`)
  return data.data
}

export async function createProject(payload: ProjectPayload): Promise<Project> {
  const { data } = await api.post<{ data: Project }>('/projects', payload)
  return data.data
}

export async function updateProject(
  id: string,
  payload: Partial<ProjectPayload> & { status?: ProjectStatus },
): Promise<Project> {
  const { data } = await api.put<{ data: Project }>(`/projects/${id}`, payload)
  return data.data
}

export async function deleteProject(id: string): Promise<void> {
  await api.delete(`/projects/${id}`)
}
