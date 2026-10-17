const KEY = 'virgoai.seenProductions'

/** The productions this browser has already opened. Stored per viewer, so a missing or blocked store only means dots return. */
export function seenProductionIds(): Set<string> {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(KEY) ?? '[]')
    return new Set(Array.isArray(parsed) ? parsed.filter((id): id is string => typeof id === 'string') : [])
  } catch {
    return new Set()
  }
}

export function markProductionsSeen(ids: string[]): void {
  try {
    localStorage.setItem(KEY, JSON.stringify([...seenProductionIds(), ...ids]))
  } catch {
    // Storage can be blocked (private window, disabled site data). The dot simply stays until the next visit.
  }
}
