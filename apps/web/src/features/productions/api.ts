import { api } from '@/lib/api'

export type ProductionScene = {
  position: number
  title: string
  status: string
  keyframe_url: string | null
  clip_url: string | null
  error: string | null
}

export type Production = {
  id: string
  prompt: string
  status: 'queued' | 'keyframing' | 'animating' | 'assembling' | 'succeeded' | 'failed'
  error: string | null
  format: string
  format_label: string
  width: number
  height: number
  quality: string
  output_url: string | null
  reserved_tokens: number | null
  created_at: string | null
  script?: { title: string | null; logline: string | null } | null
  scenes?: ProductionScene[]
  usage?: { steps: { step: string; tokens: number; credits: number; model: string | null; units: number; unit_label: string | null }[]; total_tokens: number; total_platform_tokens: number }
}

export async function startProduction(payload: { prompt: string; format: string; quality: string; styles?: Record<string, string>; skill_key?: string; reference_url?: string }): Promise<Production> {
  const { data } = await api.post<Production>('/productions', payload)
  return data
}

export async function getProduction(id: string): Promise<Production> {
  const { data } = await api.get<Production>(`/productions/${id}`)
  return data
}

export async function listProductions(): Promise<Production[]> {
  const { data } = await api.get<{ data: Production[] }>('/productions')
  return data.data
}
