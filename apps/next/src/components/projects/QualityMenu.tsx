import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon } from '@hugeicons/core-free-icons'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'

export type VideoQuality = '720p' | '1080p'

const QUALITIES: { value: VideoQuality; note: string }[] = [
  { value: '720p', note: 'Default · faster, lower cost' },
  { value: '1080p', note: 'Sharper, higher cost' },
]

/** The video quality dropdown in the prompt box. Only full videos use it. */
export function QualityMenu({
  value,
  onChange,
  className,
}: {
  value: VideoQuality
  onChange: (value: VideoQuality) => void
  className?: string
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={`Video quality: ${value}`}
        title="Video quality"
        className={cn(
          'inline-flex h-9 items-center gap-1.5 rounded-full border border-border px-3 font-mono text-xs text-foreground transition-colors hover:bg-muted/40',
          className,
        )}
      >
        {value}
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" side="top" collisionPadding={24} className="w-56">
        {QUALITIES.map((quality) => (
          <DropdownMenuItem key={quality.value} onClick={() => onChange(quality.value)} className={cn(value === quality.value && 'bg-brand/15')}>
            <span className="font-mono">{quality.value}</span>
            <span className="flex-1 text-right text-xs text-muted-foreground">{quality.note}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
