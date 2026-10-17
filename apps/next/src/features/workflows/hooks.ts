import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as workflowsApi from './api'
import type { StartWorkflowPayload } from './api'
import { isWorkflowActive } from './types'

function useInvalidateWorkflow(projectId: string) {
  const queryClient = useQueryClient()
  return () => {
    queryClient.invalidateQueries({ queryKey: ['workflow', projectId] })
    queryClient.invalidateQueries({ queryKey: ['projects'] })
  }
}

export function useProjectWorkflow(projectId: string | undefined) {
  return useQuery({
    queryKey: ['workflow', projectId],
    enabled: Boolean(projectId),
    retry: false,
    queryFn: async () => {
      try {
        return await workflowsApi.getProjectWorkflow(projectId as string)
      } catch (error) {
        if (error instanceof AxiosError && error.response?.status === 404) {
          return null
        }
        throw error
      }
    },
    refetchInterval: (query) => {
      const data = query.state.data
      return data && isWorkflowActive(data.status) ? 2500 : false
    },
  })
}

export function useStartWorkflow(projectId: string) {
  const invalidate = useInvalidateWorkflow(projectId)
  return useMutation({
    mutationFn: (payload: StartWorkflowPayload) => workflowsApi.startWorkflow(projectId, payload),
    onSuccess: () => {
      invalidate()
      toast.success('Workflow started')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useWorkflowAction(
  projectId: string,
  action: 'pause' | 'resume' | 'cancel',
) {
  const invalidate = useInvalidateWorkflow(projectId)
  const messages: Record<typeof action, string> = {
    pause: 'Workflow paused',
    resume: 'Workflow resumed',
    cancel: 'Workflow cancelled',
  }

  return useMutation({
    mutationFn: (workflowId: string) => {
      if (action === 'pause') return workflowsApi.pauseWorkflow(workflowId)
      if (action === 'resume') return workflowsApi.resumeWorkflow(workflowId)
      return workflowsApi.cancelWorkflow(workflowId)
    },
    onSuccess: () => {
      invalidate()
      toast.success(messages[action])
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useApprovalDecision(projectId: string) {
  const invalidate = useInvalidateWorkflow(projectId)
  const messages = {
    approve: 'Approved — continuing',
    regenerate: 'Regenerating stage',
    revise: 'Revision saved',
    reject: 'Workflow rejected',
  } as const

  return useMutation({
    mutationFn: (input: {
      approvalId: string
      decision: keyof typeof messages
      notes?: string
      revision?: Record<string, unknown>
    }) => {
      const { approvalId, decision, notes, revision } = input
      if (decision === 'approve') return workflowsApi.approveApproval(approvalId, notes)
      if (decision === 'regenerate') return workflowsApi.regenerateApproval(approvalId, notes)
      if (decision === 'revise')
        return workflowsApi.reviseApproval(approvalId, revision ?? {}, notes)
      return workflowsApi.rejectApproval(approvalId, notes)
    },
    onSuccess: (_data, variables) => {
      invalidate()
      toast.success(messages[variables.decision])
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useActivity() {
  return useQuery({
    queryKey: ['activity'],
    queryFn: () => workflowsApi.getActivity(),
    refetchInterval: (query) => {
      const data = query.state.data
      return data?.some((workflow) => isWorkflowActive(workflow.status)) ? 2500 : false
    },
  })
}
