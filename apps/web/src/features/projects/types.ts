export const PROJECT_TYPES = [
  'idea',
  'youtube_url',
  'transcript',
  'uploaded_video',
  'script',
] as const

export const PROJECT_STATUSES = ['draft', 'active', 'completed', 'archived'] as const

export const PROJECT_FORMATS = [
  'landscape_16_9',
  'vertical_9_16',
  'square_1_1',
  'portrait_4_5',
] as const

export type ProjectFormat = (typeof PROJECT_FORMATS)[number]

export type ProjectType = (typeof PROJECT_TYPES)[number]
export type ProjectStatus = (typeof PROJECT_STATUSES)[number]

export type Project = {
  id: string
  title: string
  description: string | null
  type: ProjectType
  format: ProjectFormat
  format_label: string
  dimensions: { width: number; height: number }
  preview_url: string | null
  status: ProjectStatus
  settings: Record<string, unknown>
  created_at: string
  updated_at: string
}

export type Paginated<T> = {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const PROJECT_TYPE_LABELS: Record<ProjectType, string> = {
  idea: 'Raw idea',
  youtube_url: 'YouTube URL',
  transcript: 'Transcript',
  uploaded_video: 'Uploaded video',
  script: 'Script',
}

export const PROJECT_FORMAT_INFO: Record<ProjectFormat, { label: string; use: string }> = {
  landscape_16_9: { label: 'Landscape 16:9', use: 'YouTube, desktop, presentations' },
  vertical_9_16: { label: 'Vertical 9:16', use: 'Shorts, Reels, TikTok' },
  square_1_1: { label: 'Square 1:1', use: 'Feed posts, LinkedIn' },
  portrait_4_5: { label: 'Portrait 4:5', use: 'Instagram feed' },
}
