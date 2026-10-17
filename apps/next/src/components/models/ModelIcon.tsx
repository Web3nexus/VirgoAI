import { BRAND_PATHS } from './brandPaths'
import { cn } from '@/lib/utils'

/** The provider's own mark when we have it, otherwise the model's first letter. */
export function ModelIcon({ brand, name, className }: { brand?: string; name: string; className?: string }) {
  const path = brand ? BRAND_PATHS[brand] : undefined
  if (path) {
    return (
      <svg viewBox="0 0 24 24" aria-hidden className={cn('size-4 shrink-0 fill-current', className)}>
        <path d={path} />
      </svg>
    )
  }
  return (
    <span aria-hidden className={cn('grid size-4 shrink-0 place-items-center rounded-sm bg-muted text-[10px] font-semibold leading-none', className)}>
      {name.charAt(0)}
    </span>
  )
}
