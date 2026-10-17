import { AlertTriangle, ImageOff, Loader2 } from 'lucide-react'
import { parseDirectorScript } from '@/features/tools/director'
import type { Generation } from '@/features/tools/types'
import { DirectorScriptView } from './DirectorScriptView'
import { cn } from '@/lib/utils'

/** Shows one saved run. Text as text, images as images, failures with the provider's reason. */
export function GenerationOutput({
  generation,
  className,
  compact = false,
}: {
  generation: Generation
  className?: string
  /** Clips the output to a fixed frame. Used in lists so long outputs never grow a card. */
  compact?: boolean
}) {
  if (generation.status === 'running') {
    return (
      <div className={cn('flex h-full min-h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border p-6 text-center', className)}>
        <Loader2 className="size-5 animate-spin text-muted-foreground" />
        <p className="text-sm text-muted-foreground">Rendering at the provider. This page updates on its own.</p>
      </div>
    )
  }

  if (generation.status === 'failed') {
    return (
      <div className={cn('flex items-start gap-3 rounded-xl border border-red-400/30 bg-red-400/5 p-4', className)}>
        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-red-400" />
        <p className="text-sm text-red-300">{generation.error ?? 'The run failed.'}</p>
      </div>
    )
  }

  const director = generation.output_type === 'text' && generation.options?.skill === 'director'
    ? parseDirectorScript(generation.output_text)
    : null

  if (director && !compact) {
    return <DirectorScriptView script={director} className={className} />
  }

  if (generation.output_type === 'text') {
    return (
      <p
        className={cn(
          'whitespace-pre-wrap text-sm leading-relaxed',
          compact ? 'line-clamp-4' : 'max-h-[70vh] overflow-y-auto',
          className,
        )}
      >
        {generation.output_text}
      </p>
    )
  }

  if (generation.output_type === 'image' && generation.output_url) {
    return (
      <img
        src={generation.output_url}
        alt={generation.prompt}
        className={cn(
          compact ? 'size-full object-cover' : 'max-h-[70vh] w-full object-contain',
          'rounded-xl',
          className,
        )}
      />
    )
  }

  if (generation.output_type === 'video' && generation.output_url) {
    return (
      <video
        src={generation.output_url}
        controls
        preload="metadata"
        aria-label={generation.prompt}
        className={cn(
          compact ? 'size-full object-cover' : 'max-h-[70vh] w-full object-contain',
          'rounded-xl bg-black',
          className,
        )}
      />
    )
  }

  return (
    <div className={cn('flex flex-col items-center gap-2 rounded-xl border border-dashed border-border p-8 text-center', className)}>
      <ImageOff className="size-5 text-muted-foreground" />
      <p className="text-sm text-muted-foreground">No output was saved for this run.</p>
    </div>
  )
}
