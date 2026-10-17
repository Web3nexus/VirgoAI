import { HugeiconsIcon } from '@hugeicons/react'
import { AiSparklesIcon, ArrowDown01Icon, ArrowRight01Icon, Message01Icon } from '@hugeicons/core-free-icons'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { cn } from '@/lib/utils'

/**
 * Chooses how the prompt is handled: Chat Mode answers in text, Director Mode makes what you describe.
 * Skills are chosen from the Direct your video section, not here.
 */
export function ModeMenu({
  chat,
  onChatChange,
  onBrowse,
  className,
}: {
  chat: boolean
  onChatChange: (chat: boolean) => void
  onBrowse: () => void
  className?: string
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={`Mode: ${chat ? 'Chat Mode' : 'Director Mode'}`}
        className={cn(
          'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-sm transition-colors',
          'border-brand/60 bg-brand/15 text-foreground',
          className,
        )}
      >
        <HugeiconsIcon icon={chat ? Message01Icon : AiSparklesIcon} size={16} strokeWidth={1.75} className="text-brand" aria-hidden />
        {chat ? 'Chat Mode' : 'Director Mode'}
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" side="top" collisionPadding={24} className="w-72">
        <DropdownMenuItem onClick={() => onChatChange(true)} className={cn('flex-col items-start gap-0.5', chat && 'bg-brand/15')}>
          <span className="font-medium">Chat Mode</span>
          <span className="text-xs text-muted-foreground">Answers and plans in text. No video or image.</span>
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onChatChange(false)} className={cn('flex-col items-start gap-0.5', !chat && 'bg-brand/15')}>
          <span className="font-medium">Director Mode</span>
          <span className="text-xs text-muted-foreground">Writes a script and makes what you describe.</span>
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={onBrowse} className="justify-between">
          Browse Skill Library
          <HugeiconsIcon icon={ArrowRight01Icon} size={14} strokeWidth={2} aria-hidden />
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
