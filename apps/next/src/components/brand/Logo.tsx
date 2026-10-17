import { cn } from '@/lib/utils'

export function Logo({ className }: { className?: string }) {
  return (
    <div className={cn('flex items-center gap-2.5', className)}>
      <div className="relative grid size-8 place-items-center rounded-lg bg-brand text-primary-foreground shadow-[0_0_20px_var(--brand-glow)]">
        <svg viewBox="0 0 24 24" className="size-4.5" aria-hidden="true">
          <path
            d="M12 2.5 14.2 9l6.3 2.1-6.3 2.2L12 20l-2.2-6.7L3.5 11l6.3-2.1L12 2.5Z"
            fill="currentColor"
          />
        </svg>
      </div>
      <span className="font-heading text-lg font-semibold tracking-tight">
        virgo<span className="text-brand">Ai</span>
      </span>
    </div>
  )
}
