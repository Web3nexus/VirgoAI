import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as clipsApi from './api'
import type { ClipRun, ClipSource } from './types'

const SOURCE_BUSY = ['uploaded', 'probing', 'transcribing']
const RUN_BUSY = ['queued', 'running']

export function useClipSources() {
  return useQuery({ queryKey: ['clips', 'sources'], queryFn: clipsApi.listSources })
}

/** Follows a source until its transcript is ready or it fails. */
export function useClipSource(id: string | null) {
  return useQuery({
    queryKey: ['clips', 'source', id],
    queryFn: () => clipsApi.getSource(id as string),
    enabled: Boolean(id),
    refetchInterval: (query) => (query.state.data && SOURCE_BUSY.includes(query.state.data.status) ? 5000 : false),
  })
}

/** Follows a clip request until its clips are cut or it fails. */
export function useClipRun(id: string | null) {
  return useQuery({
    queryKey: ['clips', 'run', id],
    queryFn: () => clipsApi.getRun(id as string),
    enabled: Boolean(id),
    refetchInterval: (query) => (query.state.data && RUN_BUSY.includes(query.state.data.status) ? 4000 : false),
  })
}

export function useAddSource(onAdded: (source: ClipSource) => void) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: { file?: File; youtubeUrl?: string; onProgress?: (p: number) => void }) =>
      input.file ? clipsApi.uploadVideo(input.file, input.onProgress ?? (() => undefined)) : clipsApi.addYoutube(input.youtubeUrl as string),
    onSuccess: (source) => {
      queryClient.invalidateQueries({ queryKey: ['clips', 'sources'] })
      onAdded(source)
      toast.success(source.kind === 'youtube' ? 'YouTube video added' : 'Upload received. Preparing the transcript.')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useRequestClips(sourceId: string, onStarted: (run: ClipRun) => void) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: clipsApi.ClipRequest) => clipsApi.requestClips(sourceId, payload),
    onSuccess: (run) => {
      queryClient.invalidateQueries({ queryKey: ['clips', 'source', sourceId] })
      onStarted(run)
      toast('Finding the moments. This can take a few minutes for a long video.')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

const REMIX_BUSY = ['queued', 'running']

export function useRemix(id: string | null) {
  return useQuery({
    queryKey: ['clips', 'remix', id],
    queryFn: () => clipsApi.getRemix(id as string),
    enabled: Boolean(id),
    refetchInterval: (query) => (query.state.data && REMIX_BUSY.includes(query.state.data.status) ? 4000 : false),
  })
}

export function useRequestRemix(sourceId: string, onStarted: (remixId: string) => void) {
  return useMutation({
    mutationFn: (payload: { instructions?: string; model_key?: string }) => clipsApi.requestRemix(sourceId, payload),
    onSuccess: (remix) => {
      onStarted(remix.id)
      toast('Writing an original story from the transcript.')
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useSplitSource(sourceId: string, onStarted: (runId: string) => void) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (partSeconds: number) => clipsApi.splitSource(sourceId, partSeconds),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['clips', 'source', sourceId] })
      onStarted(result.run_id)
      toast(`Cutting ${result.parts} parts. Long videos take a while.`)
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}
