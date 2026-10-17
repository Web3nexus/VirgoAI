import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as productionsApi from './api'

const BUSY = ['queued', 'keyframing', 'animating', 'assembling']

export function useStartProduction() {
  return useMutation({
    mutationFn: productionsApi.startProduction,
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

/** Follows a full video until it finishes or fails. */
export function useProduction(id: string | undefined) {
  return useQuery({
    queryKey: ['production', id],
    queryFn: () => productionsApi.getProduction(id as string),
    enabled: Boolean(id),
    refetchInterval: (query) => (query.state.data && BUSY.includes(query.state.data.status) ? 8000 : false),
  })
}

export function useProductions() {
  return useQuery({ queryKey: ['productions'], queryFn: productionsApi.listProductions })
}
