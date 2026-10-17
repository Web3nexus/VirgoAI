export type ClipSourceStatus = 'uploaded' | 'probing' | 'transcribing' | 'ready' | 'failed'

export type ClipSource = {
  id: string
  kind: 'upload' | 'youtube'
  status: ClipSourceStatus
  name: string | null
  error: string | null
  duration_seconds: number | null
  width: number | null
  height: number | null
  youtube_embed_url: string | null
  youtube_watch_url: string | null
  transcript_segments: number
  can_clip: boolean
  can_find_moments: boolean
  created_at: string | null
  runs?: ClipRun[]
}

export type Clip = {
  id: string
  position: number
  title: string
  reason: string | null
  start_seconds: number
  end_seconds: number
  duration_seconds: number
  url: string | null
}

export type ClipRunStatus = 'queued' | 'running' | 'succeeded' | 'failed'

export type ClipRun = {
  id: string
  source_id: string
  request: string
  status: ClipRunStatus
  error: string | null
  count: number
  min_seconds: number
  max_seconds: number
  model_key: string
  price_usd: number | null
  clips: Clip[]
  created_at: string | null
}
