import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as projectsApi from './api'
import type { ProjectListParams, ProjectPayload } from './api'
import type { ProjectStatus } from './types'

export function useProjects(params: ProjectListParams = {}) {
  return useQuery({
    queryKey: ['projects', params],
    queryFn: () => projectsApi.listProjects(params),
  })
}

export function useProject(id: string | undefined) {
  return useQuery({
    queryKey: ['projects', id],
    queryFn: () => projectsApi.getProject(id as string),
    enabled: Boolean(id),
  })
}

export function useCreateProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ProjectPayload) => projectsApi.createProject(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
      toast.success('Project created')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useUpdateProject(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<ProjectPayload> & { status?: ProjectStatus }) =>
      projectsApi.updateProject(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
      toast.success('Project updated')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useDeleteProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => projectsApi.deleteProject(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['projects'] })
      toast.success('Project deleted')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}
