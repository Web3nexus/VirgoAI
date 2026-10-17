import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type StyleOption = { key: string; label: string; description: string }
export type StyleCatalog = { groups: { environment: StyleOption[]; character: StyleOption[]; look: StyleOption[] } }
export type StyleChoice = { environment?: string; character?: string; look?: string }

export function useStyles() {
  return useQuery({
    queryKey: ['styles'],
    queryFn: async () => (await api.get<StyleCatalog>('/styles')).data,
    staleTime: Infinity,
  })
}
