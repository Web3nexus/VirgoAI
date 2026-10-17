import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import * as usageApi from './api'

/** Re-prices an action as the user types. Debounced, so typing does not send a request per key. */
export function useQuote(payload: usageApi.QuotePayload | null, delayMs = 400) {
  const [debounced, setDebounced] = useState(payload)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(payload), delayMs)
    return () => clearTimeout(timer)
  }, [JSON.stringify(payload), delayMs]) // eslint-disable-line react-hooks/exhaustive-deps

  return useQuery({
    queryKey: ['quote', debounced],
    queryFn: () => usageApi.getQuote(debounced as usageApi.QuotePayload),
    enabled: debounced !== null,
    staleTime: 30_000,
  })
}

export function useUsage(type: string | null, id: string | null) {
  return useQuery({
    queryKey: ['usage', type, id],
    queryFn: () => usageApi.getUsage(type as string, id as string),
    enabled: Boolean(type && id),
  })
}
