import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

/** What a model makes. A skill's mode decides which of these it uses. */
export type ModelCapability = 'video' | 'image' | 'text'

export type ModelOption = {
  key: string
  name: string
  provider_key: string
  /** False when the model cannot run yet. `reason` says why (for example, the API key is missing). */
  usable: boolean
  reason: string | null
  price_label: string | null
}

export type ModelOptions = Record<ModelCapability, { default: string | null; models: ModelOption[] }>

/** The models the studio can run, grouped by what they make. Only the server knows which ones are usable. */
export function useModelOptions() {
  return useQuery({
    queryKey: ['model-options'],
    queryFn: async () => (await api.get<{ data: ModelOptions }>('/model-options')).data.data,
    staleTime: 60_000,
  })
}
