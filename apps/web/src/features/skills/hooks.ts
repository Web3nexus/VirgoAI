import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ProjectFormat } from '@/features/projects/types'

/** Where a skill runs. "text" writes a script through the story tool, so no media is rendered. */
export type SkillMode = 'full_video' | 'image' | 'audio' | 'text' | 'agent'

export type Skill = {
  key: string
  label: string
  category: string
  mode: SkillMode
  icon: 'video' | 'image' | 'audio' | 'marketing' | 'explainer' | 'music' | 'storyboard' | 'agent'
  description: string
  /** What the system makes for this skill, in plain words. */
  produces: string
  defaults: { format?: ProjectFormat; quality?: '720p' | '1080p' }
  /** The guided form a Quick Start shows. Empty for skills that take a plain prompt. */
  inputs: QuickStartField[]
  /** What the skill makes (its mode decides it). Null for skills that make no media. */
  capability: 'video' | 'image' | 'text' | null
  /** The model the skill runs with unless the user picks another. */
  default_model: string | null
}

/** One answer in a Quick Start form. The server writes the answers into the prompt, under `brief`. */
export type QuickStartField = {
  key: string
  type: 'text' | 'textarea' | 'choice' | 'slider' | 'reference'
  label: string
  brief: string | null
  required: boolean
  max?: number
  placeholder?: string
  options?: Record<string, string>
  default?: string | number | null
  min?: number
  step?: number
  unit?: string
}

/** The whole library. Filtering by category and search happens in the picker, so one request serves every view. */
export function useSkills() {
  return useQuery({
    queryKey: ['skills'],
    queryFn: async () => (await api.get<{ data: Skill[] }>('/skills')).data.data,
    staleTime: Infinity,
  })
}

/** The cover image for a skill. Generated placeholder art, one per skill, in public/skills. */
export function skillCover(key: string): string {
  return `/skills/${key}.svg`
}

/** What a skill makes, as shown on its card. "Script" writes text through the story tool. */
export const SKILL_MODE_LABEL: Record<SkillMode, string> = {
  full_video: 'Video',
  image: 'Image',
  audio: 'Audio',
  text: 'Script',
  agent: 'Any',
}
