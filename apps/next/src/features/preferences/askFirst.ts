import { useEffect, useState } from 'react'

const KEY = 'virgo.askFirst'

/**
 * Whether a run waits for a confirmation before it starts. Kept on this device; defaults to asking first.
 */
export function useAskFirst() {
  const [value, setValue] = useState<boolean>(() => {
    try {
      return localStorage.getItem(KEY) !== 'false'
    } catch {
      return true
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(KEY, String(value))
    } catch {
      // Storage can be blocked (private windows). The choice then lasts for this page only.
    }
  }, [value])

  return [value, setValue] as const
}
