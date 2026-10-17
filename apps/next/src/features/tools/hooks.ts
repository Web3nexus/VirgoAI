import { useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as toolsApi from './api'
import type { Generation, RunToolPayload } from './types'

/** How often a running render is checked, in milliseconds. */
const POLL_INTERVAL_MS = 3000

export function useTools() {
  return useQuery({ queryKey: ['tools'], queryFn: toolsApi.listTools, staleTime: 60_000 })
}

export function useGenerations(tool?: string, limit = 30) {
  return useQuery({
    queryKey: ['generations', tool ?? 'all', limit],
    queryFn: () => toolsApi.listGenerations({ tool, limit }),
  })
}

export function useRunTool(tool: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: RunToolPayload) => toolsApi.runTool(tool, payload),
    onSuccess: (generation) => {
      queryClient.invalidateQueries({ queryKey: ['generations'] })
      if (generation.status === 'running') {
        toast('Sent to the provider. Rendering can take a few minutes.')
      } else if (generation.status === 'succeeded') {
        toast.success('Done')
      } else {
        toast.error(generation.error ?? 'The run failed')
      }
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

/**
 * Follows one run until it leaves "running". Finished runs refresh the history and say so once.
 */
export function useGenerationPoll(generation: Generation | null) {
  const queryClient = useQueryClient()
  const runId = generation?.id

  const query = useQuery({
    queryKey: ['generation', runId],
    queryFn: () => toolsApi.getGeneration(runId as string),
    enabled: Boolean(runId) && generation?.status === 'running',
    refetchInterval: (state) => (state.state.data?.status === 'running' ? POLL_INTERVAL_MS : false),
  })

  const finished = query.data && query.data.id === runId && query.data.status !== 'running' ? query.data : null

  useEffect(() => {
    if (!finished) return
    queryClient.invalidateQueries({ queryKey: ['generations'] })
    if (finished.status === 'succeeded') {
      toast.success('Your render is ready')
    } else {
      toast.error(finished.error ?? 'The run failed')
    }
  }, [finished, queryClient])

  return query
}
