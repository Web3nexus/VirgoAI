export type ToolOutput = 'text' | 'image' | 'video' | 'audio'

export type ToolModel = {
  key: string
  name: string
  provider_key: string
  usable: boolean
  reason: string | null
  /** What one unit costs the user, after margin. */
  price_label: string | null
}

export type ToolChoice = {
  label: string
  default: string
  values: Record<string, string>
}

export type StudioTool = {
  key: string
  label: string
  description: string
  capability: string
  output: ToolOutput
  sizes: boolean
  default_size: string
  choices: Record<string, ToolChoice>
  voices: string[]
  ready: boolean
  models: ToolModel[]
}

export type Generation = {
  id: string
  tool: string
  capability: string
  provider_key: string | null
  model_key: string | null
  project_id: string | null
  prompt: string
  options: Record<string, unknown>
  status: 'running' | 'succeeded' | 'failed'
  output_type: ToolOutput | null
  output_text: string | null
  output_url: string | null
  /** The provider's own link to the file. Paste it as a keyframe for a video run. */
  provider_url: string | null
  error: string | null
  cost_usd: string | null
  created_at: string
}

export type RunToolPayload = {
  prompt: string
  model_key?: string
  project_id?: string
  size?: string
  script_type?: string
  skill?: string
  /** A skill from the library. Its instructions are what the AI follows for this run. */
  skill_key?: string
  image_url?: string
  styles?: { environment?: string; character?: string; look?: string }
  resolution?: '480p' | '720p' | '1080p'
}
