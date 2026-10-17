import { api } from '@/lib/api'
import type { ClipRun, ClipSource } from './types'

export async function listSources(): Promise<ClipSource[]> {
  const { data } = await api.get<{ data: ClipSource[] }>('/clip-sources')
  return data.data
}

export async function getSource(id: string): Promise<ClipSource> {
  const { data } = await api.get<ClipSource>(`/clip-sources/${id}`)
  return data
}

export async function uploadVideo(file: File, onProgress: (percent: number) => void): Promise<ClipSource> {
  const body = new FormData()
  body.append('file', file)
  const { data } = await api.post<ClipSource>('/clip-sources', body, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: (event) => {
      if (event.total) onProgress(Math.round((event.loaded / event.total) * 100))
    },
  })
  return data
}

export async function addYoutube(url: string): Promise<ClipSource> {
  const { data } = await api.post<ClipSource>('/clip-sources', { youtube_url: url })
  return data
}

export type ClipRequest = {
  request: string
  count: number
  min_seconds: number
  max_seconds: number
  model_key?: string
}

export async function requestClips(sourceId: string, payload: ClipRequest): Promise<ClipRun> {
  const { data } = await api.post<ClipRun>(`/clip-sources/${sourceId}/runs`, payload)
  return data
}

export async function getRun(id: string): Promise<ClipRun> {
  const { data } = await api.get<ClipRun>(`/clip-runs/${id}`)
  return data
}

export type Remix = {
  id: string
  source_id: string
  status: 'queued' | 'running' | 'succeeded' | 'failed'
  title: string | null
  logline: string | null
  story: string | null
  error: string | null
  original: boolean
  charged_credits: number | null
  usage: { steps: { step: string; tokens: number; credits: number; model: string | null; units: number; unit_label: string | null }[]; total_tokens: number; total_credits: number }
}

export async function requestRemix(sourceId: string, payload: { instructions?: string; model_key?: string }): Promise<Remix> {
  const { data } = await api.post<Remix>(`/clip-sources/${sourceId}/remixes`, payload)
  return data
}

export async function getRemix(id: string): Promise<Remix> {
  const { data } = await api.get<Remix>(`/remixes/${id}`)
  return data
}

export async function splitSource(sourceId: string, partSeconds: number): Promise<{ run_id: string; parts: number }> {
  const { data } = await api.post<{ run_id: string; parts: number }>(`/clip-sources/${sourceId}/split`, { part_seconds: partSeconds })
  return data
}

export async function trimClip(clipId: string, start: number, end: number): Promise<{ url: string; start_seconds: number; end_seconds: number }> {
  const { data } = await api.post<{ url: string; start_seconds: number; end_seconds: number }>(`/clips/${clipId}/trim`, { start, end })
  return data
}

export async function trimGeneration(generationId: string, start: number, end: number): Promise<{ output_url: string }> {
  const { data } = await api.post<{ output_url: string }>(`/generations/${generationId}/trim`, { start, end })
  return data
}
