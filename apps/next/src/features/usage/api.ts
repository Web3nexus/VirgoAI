import { api } from '@/lib/api'

export type Quote = {
  affordable: boolean
  price_usd: number | null
  tokens: number | null
  balance_tokens: number
  label: string | null
  unit: string | null
  quantity: number | null
  estimate: boolean
  message: string | null
}

export type QuotePayload =
  | { kind: 'tool'; tool: string; model_key?: string; size?: string; prompt?: string; duration?: number }
  | { kind: 'clips'; source_id: string; model_key?: string; request?: string }
  | { kind: 'transcribe'; source_id: string }

export async function getQuote(payload: QuotePayload): Promise<Quote> {
  const { data } = await api.post<Quote>('/quote', payload)
  return data
}

export type UsageStep = {
  step: string
  model: string | null
  tokens: number
  units: number
  unit_label: string | null
  platform_tokens: number
}

export type UsageSummary = {
  steps: UsageStep[]
  total_tokens: number
  total_platform_tokens: number
}

export async function getUsage(type: string, id: string): Promise<UsageSummary> {
  const { data } = await api.get<UsageSummary>(`/usage/${type}/${id}`)
  return data
}
