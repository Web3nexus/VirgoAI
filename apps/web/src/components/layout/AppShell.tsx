import { useState, type ReactNode } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { HugeiconsIcon, type IconSvgElement } from '@hugeicons/react'
import {
  AiSparklesIcon,
  Archive01Icon,
  Bookmark01Icon,
  Book01Icon,
  Camera01Icon,
  Image01Icon,
  Layers01Icon,
  Mic01Icon,
  Megaphone01Icon,
  VideoAiIcon,
  Folder01Icon,
  Home01Icon,
  Logout01Icon,
  Settings01Icon,
  SidebarLeftIcon,
  Notification01Icon,
  PlusSignIcon,
  CreditCardIcon,
  Film01Icon,
} from '@hugeicons/core-free-icons'
import { FollowUsMenu } from '@/components/notifications/FollowUsMenu'
import { NotificationsMenu } from '@/components/notifications/NotificationsMenu'
import { Logo } from '@/components/brand/Logo'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuGroup,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useCurrentUser, useLogout } from '@/features/auth/hooks'
import { cn } from '@/lib/utils'

type NavItem = {
  to: string
  label: string
  icon: IconSvgElement
  end?: boolean
  soon?: boolean
}

const HOME: NavItem[] = [
  { to: '/', label: 'Home', icon: Home01Icon, end: true },
  { to: '/projects', label: 'Projects', icon: Folder01Icon },
  { to: '/clips', label: 'Clips', icon: Film01Icon },
  { to: '/activity', label: 'Activity', icon: Notification01Icon },
  { to: '/billing', label: 'Billing', icon: CreditCardIcon },
]

const TOOLS: NavItem[] = [
  { to: '/skills', label: 'Skills', icon: AiSparklesIcon },
  { to: '/tools/story', label: 'Story', icon: AiSparklesIcon },
  { to: '/tools/image', label: 'Image', icon: Image01Icon },
  { to: '/tools/video', label: 'Video', icon: VideoAiIcon },
  { to: '/tools/character', label: 'Character', icon: Camera01Icon },
  { to: '/tools/narration', label: 'Narration', icon: Mic01Icon },
  { to: '/tools/thumbnail', label: 'Thumbnail', icon: Layers01Icon },
]

const ASSETS: NavItem[] = [
  { to: '/library', label: 'Asset library', icon: Archive01Icon },
  { to: '#', label: 'Brand kit', icon: Bookmark01Icon, soon: true },
]

const INSPIRE: NavItem[] = [
  { to: '#', label: 'Templates', icon: Layers01Icon, soon: true },
  { to: '#', label: 'Tutorials', icon: Book01Icon, soon: true },
  { to: '#', label: 'Campaigns', icon: Megaphone01Icon, soon: true },
]

function SidebarSection({ title, items, collapsed }: { title: string; items: NavItem[]; collapsed: boolean }) {
  return (
    <div className="mt-5">
      {!collapsed && (
        <p className="px-3 pb-2 text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground/70">{title}</p>
      )}
      <ul className="space-y-0.5">
        {items.map((item) => (
          <li key={item.label}>
            <NavLink
              to={item.to}
              end={item.end}
              title={collapsed ? item.label : undefined}
              onClick={(event) => item.soon && event.preventDefault()}
              aria-disabled={item.soon || undefined}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-muted-foreground transition-colors',
                  collapsed && 'justify-center px-0',
                  item.soon
                    ? 'cursor-not-allowed opacity-45'
                    : 'hover:bg-sidebar-accent hover:text-foreground',
                  isActive && !item.soon && 'bg-sidebar-accent text-foreground',
                )
              }
            >
              <HugeiconsIcon icon={item.icon} size={18} strokeWidth={1.75} aria-hidden />
              {!collapsed && <span className="truncate">{item.label}</span>}
              {!collapsed && item.soon && (
                <span className="ml-auto rounded-full border border-border px-1.5 py-px text-[10px] text-muted-foreground">Soon</span>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </div>
  )
}

export function AppShell() {
  const { data: user } = useCurrentUser()
  const logout = useLogout()
  const [collapsed, setCollapsed] = useState(false)

  const initials = (user?.name ?? '?')
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return (
    <div className="flex min-h-svh bg-background p-3 text-foreground">
      <aside
        className={cn(
          'sticky top-3 hidden h-[calc(100svh-1.5rem)] shrink-0 flex-col rounded-2xl border border-sidebar-border bg-sidebar/90 p-3 backdrop-blur-[14px] transition-[width] duration-200 md:flex',
          collapsed ? 'w-[72px]' : 'w-64',
        )}
      >
        <div className={cn('flex items-center py-1', collapsed ? 'justify-center' : 'justify-between px-1')}>
          {!collapsed && <Logo />}
          <Button
            variant="ghost"
            size="icon"
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            onClick={() => setCollapsed((value) => !value)}
          >
            <HugeiconsIcon icon={SidebarLeftIcon} size={18} strokeWidth={1.75} aria-hidden />
          </Button>
        </div>

        <nav aria-label="Studio" className="flex-1 overflow-y-auto pb-4">
          <SidebarSection title="Studio" items={HOME} collapsed={collapsed} />
          <SidebarSection title="Tools" items={TOOLS} collapsed={collapsed} />
          <SidebarSection title="Assets" items={ASSETS} collapsed={collapsed} />
          <SidebarSection title="Inspire" items={INSPIRE} collapsed={collapsed} />
        </nav>

        <div className="border-t border-sidebar-border pt-3">
          <DropdownMenu>
            <DropdownMenuTrigger
              className={cn(
                'flex w-full items-center gap-3 rounded-lg p-2 text-left transition-colors hover:bg-sidebar-accent',
                collapsed && 'justify-center',
              )}
            >
              <Avatar className="size-8">
                <AvatarFallback className="bg-brand/15 text-xs text-brand">{initials}</AvatarFallback>
              </Avatar>
              {!collapsed && (
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{user?.name ?? 'Loading…'}</p>
                  <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
                </div>
              )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
              <DropdownMenuGroup>
                <DropdownMenuLabel>My account</DropdownMenuLabel>
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuItem disabled>
                <HugeiconsIcon icon={Settings01Icon} size={16} strokeWidth={1.75} />
                Settings
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={logout}>
                <HugeiconsIcon icon={Logout01Icon} size={16} strokeWidth={1.75} />
                Sign out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col md:pl-3">
        <header className="sticky top-3 z-20 mb-3 flex h-14 items-center gap-3 rounded-2xl border border-border/70 bg-background/80 px-4 backdrop-blur-[14px]">
          <div className="md:hidden">
            <Logo />
          </div>
          <div className="ml-auto flex items-center gap-2">
            <FollowUsMenu />
            <NotificationsMenu />
            <a
              href="/billing"
              className="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-xs text-muted-foreground hover:text-foreground"
              aria-label="Credits balance"
            >
              <span className="text-foreground">{(user?.balance_tokens ?? 0).toLocaleString('en-US', { maximumFractionDigits: 1 })}</span> tokens
            </a>
            <Button
              variant="ghost"
              size="sm"
              className="gap-2 text-muted-foreground"
              nativeButton={false}
              render={<a href="/projects" />}
            >
              Projects
            </Button>
            <Button size="sm" className="gap-1.5" nativeButton={false} render={<a href="#new-production" />}>
              <HugeiconsIcon icon={PlusSignIcon} size={14} strokeWidth={2} aria-hidden />
              New production
            </Button>
          </div>
        </header>

        <main className="flex-1">
          <div className="mx-auto w-full max-w-5xl px-2 pb-16 pt-6 sm:px-4">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}

export function ShellSection({ children }: { children: ReactNode }) {
  return <section className="mt-12">{children}</section>
}
