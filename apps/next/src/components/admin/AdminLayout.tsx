import { useState } from 'react'
import type { ReactNode } from 'react'
import { NavLink, useNavigate } from '@/lib/router'
import { HugeiconsIcon, type IconSvgElement } from '@hugeicons/react'
import {
  AiBrain01Icon,
  Coins01Icon,
  CreditCardIcon,
  Key01Icon,
  DashboardSquare01Icon,
  Home01Icon,
  Logout01Icon,
  Mail01Icon,
  Money01Icon,
  ScrollIcon,
  Settings01Icon,
  Shield01Icon,
  UserMultiple02Icon,
} from '@hugeicons/core-free-icons'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/features/auth/store'
import { cn } from '@/lib/utils'

type NavItem = { to: string; label: string; icon: IconSvgElement; end?: boolean }

/** Grouped navigation. Business and platform are the owner's; team and customers are people. */
const NAV_GROUPS: { title: string | null; items: NavItem[] }[] = [
  { title: null, items: [{ to: '/securegate', label: 'Dashboard', icon: DashboardSquare01Icon, end: true }] },
  {
    title: 'Business',
    items: [
      { to: '/securegate/billing', label: 'Billing & plans', icon: CreditCardIcon },
      { to: '/securegate/pricing', label: 'Pricing & margin', icon: Money01Icon },
    ],
  },
  {
    title: 'Platform',
    items: [
      { to: '/securegate/providers', label: 'AI providers', icon: AiBrain01Icon },
      { to: '/securegate/keys', label: 'API keys', icon: Key01Icon },
      { to: '/securegate/payments', label: 'Payments', icon: Coins01Icon },
      { to: '/securegate/email', label: 'Email templates', icon: Mail01Icon },
      { to: '/securegate/system', label: 'Settings', icon: Settings01Icon },
      { to: '/securegate/audit', label: 'Audit log', icon: ScrollIcon },
    ],
  },
  { title: 'Team', items: [{ to: '/securegate/team', label: 'Admin management', icon: Shield01Icon }] },
  { title: 'Customers', items: [{ to: '/securegate/users', label: 'User management', icon: UserMultiple02Icon }] },
]

function NavSection({ title, items }: { title: string | null; items: NavItem[] }) {
  return (
    <div className="mt-5 first:mt-0">
      {title && <p className="px-3 pb-2 text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground/70">{title}</p>}
      <ul className="space-y-0.5">
        {items.map((item) => (
          <li key={item.to}>
            <NavLink
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-foreground',
                  isActive && 'bg-sidebar-accent text-foreground',
                )
              }
            >
              <HugeiconsIcon icon={item.icon} size={18} strokeWidth={1.75} aria-hidden />
              <span className="truncate">{item.label}</span>
            </NavLink>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** The owner console at /securegate. Rendered only for super admins by SecureGatePage. */
export function AdminLayout({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const logout = useAuthStore((state) => state.logout)

  return (
    <div className="flex min-h-svh bg-background text-foreground">
      <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col overflow-y-auto border-r border-sidebar-border bg-sidebar/90 p-3 backdrop-blur-[14px] md:flex">
        <div className="px-3 py-3">
          <p className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground/70">Owner console</p>
          <p className="font-heading text-lg font-semibold">virgoAi</p>
        </div>

        <nav aria-label="Owner console" className="flex-1">
          {NAV_GROUPS.map((group) => (
            <NavSection key={group.title ?? 'top'} title={group.title} items={group.items} />
          ))}
        </nav>

        <div className="space-y-1 border-t border-sidebar-border pt-3">
          <Button variant="ghost" className="w-full justify-start gap-3" onClick={() => navigate('/app')}>
            <HugeiconsIcon icon={Home01Icon} size={18} strokeWidth={1.75} aria-hidden />
            Open studio
          </Button>
          <Button
            variant="ghost"
            className="w-full justify-start gap-3"
            onClick={() => {
              logout()
              navigate('/securegate')
            }}
          >
            <HugeiconsIcon icon={Logout01Icon} size={18} strokeWidth={1.75} aria-hidden />
            Sign out
          </Button>
        </div>
      </aside>

      <div className="min-w-0 flex-1">
        <header className="flex items-center justify-between gap-3 border-b border-border/60 px-6 py-3 md:hidden">
          <p className="font-heading font-semibold">Owner console</p>
          <nav aria-label="Owner console (mobile)" className="flex flex-wrap gap-1">
            {NAV_GROUPS.flatMap((group) => group.items).map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  cn('rounded-lg px-2 py-1 text-xs text-muted-foreground', isActive && 'bg-sidebar-accent text-foreground')
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
        </header>
        <main className="mx-auto max-w-6xl px-6 py-8">
          {children}
        </main>
      </div>
    </div>
  )
}

/** A stat tile used across the owner pages. */
export function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-xl border border-border/60 bg-card/40 p-4">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 font-heading text-2xl font-semibold tracking-tight">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
    </div>
  )
}

export const inputClass =
  'h-9 w-full rounded-lg border border-border bg-background px-3 text-sm focus:border-brand/60 focus:outline-none'

/** Two-step delete: the first click asks, the second confirms. Nothing is removed by a single click. */
export function ConfirmButton({
  label,
  confirmLabel,
  onConfirm,
  disabled,
  icon,
}: {
  label: string
  confirmLabel: string
  onConfirm: () => void
  disabled?: boolean
  icon?: IconSvgElement
}) {
  const [armed, setArmed] = useState(false)

  if (!armed) {
    return (
      <Button variant="destructive" size="sm" className="gap-2" disabled={disabled} onClick={() => setArmed(true)}>
        {icon && <HugeiconsIcon icon={icon} size={16} strokeWidth={1.75} aria-hidden />}
        {label}
      </Button>
    )
  }

  return (
    <span className="inline-flex items-center gap-2">
      <Button
        variant="destructive"
        size="sm"
        disabled={disabled}
        onClick={() => {
          setArmed(false)
          onConfirm()
        }}
      >
        {confirmLabel}
      </Button>
      <Button variant="ghost" size="sm" onClick={() => setArmed(false)}>
        Cancel
      </Button>
    </span>
  )
}
