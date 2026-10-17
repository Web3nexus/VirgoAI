import { Film } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { Project } from '@/features/projects/types'

/**
 * A production's video preview at its real aspect ratio, drawn at a fixed height so every
 * card in a row lines up. When a render exists it plays the video. Until then it shows an
 * empty frame that says so. It never shows text in place of video.
 */
export function ProductionPreview({
  project,
  frameHeight,
  className,
}: {
  project: Project
  frameHeight: number
  className?: string
}) {
  const { width, height } = project.dimensions
  const frameWidth = Math.round((frameHeight * width) / height)

  return (
    <div
      className={cn('relative shrink-0 overflow-hidden rounded-xl border border-border bg-ink-2', className)}
      style={{ width: frameWidth, height: frameHeight }}
    >
      {project.preview_url ? (
        <video
          src={project.preview_url}
          className="size-full object-cover"
          muted
          loop
          playsInline
          preload="metadata"
          controls
          aria-label={`Preview of ${project.title}`}
        />
      ) : (
        <div className="flex size-full flex-col items-center justify-center gap-1.5 bg-gradient-to-br from-brand/15 via-ink-2 to-cyan-400/10 text-center">
          <Film className="size-5 text-muted-foreground" aria-hidden />
          <p className="text-xs text-muted-foreground">No render yet</p>
        </div>
      )}
    </div>
  )
}
