import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon } from '@hugeicons/core-free-icons'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'

/** Whether a run asks for confirmation first, or starts straight away. */
export function AskFirstMenu({ value, onChange, className }: { value: boolean; onChange: (value: boolean) => void; className?: string }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={value ? 'Ask first' : 'Make it'}
        className={cn('inline-flex h-9 items-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground transition-colors hover:text-foreground', className)}
      >
        {value ? 'Ask first' : 'Make it'}
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" side="top" collisionPadding={24} className="w-72">
        <DropdownMenuItem onClick={() => onChange(true)} className={cn('flex-col items-start gap-0.5', value && 'bg-brand/15')}>
          <span className="font-medium">Ask first</span>
          <span className="text-xs text-muted-foreground">Confirm each run before it starts.</span>
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onChange(false)} className={cn('flex-col items-start gap-0.5', !value && 'bg-brand/15')}>
          <span className="font-medium">Run automatically</span>
          <span className="text-xs text-muted-foreground">Start each run as soon as you press send.</span>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
