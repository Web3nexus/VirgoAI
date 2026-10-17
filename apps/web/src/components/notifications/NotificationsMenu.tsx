import { useNavigate } from 'react-router-dom'
import { HugeiconsIcon } from '@hugeicons/react'
import { Notification01Icon } from '@hugeicons/core-free-icons'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useProductions } from '@/features/productions/hooks'
import type { Production } from '@/features/productions/api'
import { markProductionsSeen, seenProductionIds } from './notificationsStore'
import { useState } from 'react'

const DONE = ['succeeded', 'failed']

function describe(production: Production): string {
  if (production.status === 'succeeded') return 'Your video is ready'
  if (production.status === 'failed') return 'A video could not be made'
  return 'Still in progress'
}

/**
 * Notifications from your productions: a finished or failed video shows up here until you open it.
 */
export function NotificationsMenu() {
  const navigate = useNavigate()
  const { data: productions = [] } = useProductions()
  const [seen, setSeen] = useState(() => seenProductionIds())

  const recent = productions.slice(0, 8)
  const unread = recent.filter((production) => DONE.includes(production.status) && !seen.has(production.id))

  function open(production: Production) {
    markProductionsSeen([production.id])
    setSeen(seenProductionIds())
    navigate(`/productions/${production.id}`)
  }

  function markAllRead() {
    markProductionsSeen(unread.map((production) => production.id))
    setSeen(seenProductionIds())
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={
          <Button variant="ghost" size="icon" aria-label={unread.length > 0 ? `Notifications, ${unread.length} new` : 'Notifications'} className="relative" />
        }
      >
        <HugeiconsIcon icon={Notification01Icon} size={18} strokeWidth={1.75} aria-hidden />
        {unread.length > 0 && <span className="absolute right-1.5 top-1.5 size-2 rounded-full bg-brand" aria-hidden />}
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuGroup>
          <DropdownMenuLabel className="flex items-center justify-between">
            Notifications
            {unread.length > 0 && (
              <button type="button" onClick={markAllRead} className="text-xs text-muted-foreground hover:text-foreground">
                Mark all read
              </button>
            )}
          </DropdownMenuLabel>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        {recent.length === 0 && <DropdownMenuItem disabled>Nothing yet. Your videos will show up here.</DropdownMenuItem>}
        {recent.map((production) => (
          <DropdownMenuItem key={production.id} onClick={() => open(production)} className="flex flex-col items-start gap-0.5">
            <span className={DONE.includes(production.status) && !seen.has(production.id) ? 'font-medium' : ''}>{describe(production)}</span>
            <span className="line-clamp-1 w-full text-xs text-muted-foreground">{production.prompt}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
