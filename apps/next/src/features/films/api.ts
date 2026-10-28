import { api } from '@/lib/api'

export type FilmStatus = 'queued' | 'planning' | 'board' | 'rendering' | 'assembling' | 'succeeded' | 'failed'

export type FilmBoardItem = { kind: 'cast' | 'location'; key: string; name: string; status: string; image_url: string | null }

export type FilmChapter = { index: number; seconds: number; takes: number; summary: string; output_url: string | null }

export type FilmTake = { chapter: number; position: number; seconds: number; status: string; attempts: number; error: string | null; clip_url: string | null }

export type Film = {
  id: string
  title: string | null
  brief: string
  status: FilmStatus
  error: string | null
  length_seconds: number
  format: string
  format_label: string
  quality: string
  styles: Record<string, string> | null
  takes_total: number
  takes_done: number
  progress: number
  output_url: string | null
  created_at: string | null
  style?: string | null
  logline?: string | null
  story?: string | null
  board?: FilmBoardItem[]
  chapters?: FilmChapter[]
  takes?: FilmTake[]
  pricing?: { reserved_tokens: number; spent_tokens: number }
}

export type StartFilmPayload = {
  brief: string
  title?: string
  length_seconds: number
  format: string
  quality: string
  styles?: Record<string, string>
}

export async function startFilm(payload: StartFilmPayload): Promise<Film> {
  const { data } = await api.post<Film>('/films', payload)
  return data
}

export async function getFilm(id: string): Promise<Film> {
  const { data } = await api.get<Film>(`/films/${id}`)
  return data
}

export async function listFilms(): Promise<Film[]> {
  const { data } = await api.get<{ data: Film[] }>('/films')
  return data.data
}

export async function cancelFilm(id: string): Promise<Film> {
  const { data } = await api.post<Film>(`/films/${id}/cancel`)
  return data
}
