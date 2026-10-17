import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon } from '@hugeicons/core-free-icons'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { SOCIAL_LINKS } from '@/lib/social'

/** The brand's profiles. Only networks with a URL in lib/social.ts are listed. */
export function FollowUsMenu() {
  const links = SOCIAL_LINKS.filter((link) => link.url !== '')

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        render={<Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground" />}
      >
        Follow us
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-44">
        {links.length === 0 && <DropdownMenuItem disabled>No profiles added yet</DropdownMenuItem>}
        {links.map((link) => (
          <DropdownMenuItem key={link.label} render={<a href={link.url} target="_blank" rel="noopener noreferrer" />}>
            {link.label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
