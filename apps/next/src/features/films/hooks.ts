import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as filmsApi from './api'

const BUSY: filmsApi.FilmStatus[] = ['queued', 'planning', 'board', 'rendering', 'assembling']

export function useStartFilm() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: filmsApi.startFilm,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['films'] }),
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useCancelFilm(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => filmsApi.cancelFilm(id),
    onSuccess: () => {
      toast('Film stopped')
      queryClient.invalidateQueries({ queryKey: ['film', id] })
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

/** Follows a film until it finishes or fails. */
export function useFilm(id: string | undefined) {
  return useQuery({
    queryKey: ['film', id],
    queryFn: () => filmsApi.getFilm(id as string),
    enabled: Boolean(id),
    refetchInterval: (query) => (query.state.data && BUSY.includes(query.state.data.status) ? 8000 : false),
  })
}

export function useFilms() {
  return useQuery({
    queryKey: ['films'],
    queryFn: filmsApi.listFilms,
    refetchInterval: (query) => (query.state.data?.some((film) => BUSY.includes(film.status)) ? 10000 : false),
  })
}
