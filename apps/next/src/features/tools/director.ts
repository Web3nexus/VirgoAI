/** The director script the API returns for a successful director run. */
export type DirectorScript = {
  title: string
  logline: string
  audio_mode: 'narration' | 'dialogue'
  characters: {
    character_id: string
    name: string
    identity_block: string
    voice: { voice_id: string | null; speed: number; description: string } | null
  }[]
  environments: { environment_id: string; name: string }[]
  sections: {
    section_id: string
    purpose: string
    environment_id: string
    character_ids: string[]
    start_seconds: number
    end_seconds: number
    narration: string
  }[]
  lines: {
    line_id: string
    section_id: string
    speaker: string
    text: string
    emotion?: string
  }[]
}

/** Parses a saved director result. Returns null for anything that is not a director script. */
export function parseDirectorScript(text: string | null): DirectorScript | null {
  if (!text) return null
  try {
    const value = JSON.parse(text) as Partial<DirectorScript>
    return value && Array.isArray(value.characters) && Array.isArray(value.sections) ? (value as DirectorScript) : null
  } catch {
    return null
  }
}
