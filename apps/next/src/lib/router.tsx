'use client'

import { type AnchorHTMLAttributes, type ReactNode, useEffect } from 'react'
import NextLink from 'next/link'
import { usePathname, useParams as useNextParams, useRouter, useSearchParams as useNextSearchParams } from 'next/navigation'

/**
 * The routing names the studio was written with. They now run on Next.js, so the pages keep their
 * `to=` links and `navigate()` calls without a rewrite.
 */

type LinkProps = Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'> & { to?: string; href?: string; children?: ReactNode; prefetch?: boolean; replace?: boolean; scroll?: boolean }

export function Link({ to, href, ...rest }: LinkProps) {
  return <NextLink href={to ?? href ?? '#'} {...rest} />
}

export function NavLink({ to, end, className, children, ...rest }: Omit<LinkProps, 'className'> & { end?: boolean; className?: string | ((state: { isActive: boolean }) => string) }) {
  const pathname = usePathname()
  const target = to ?? '#'
  const isActive = end ? pathname === target : pathname === target || pathname.startsWith(`${target}/`)
  const resolved = typeof className === 'function' ? className({ isActive }) : className
  return (
    <NextLink href={target} className={resolved} aria-current={isActive ? 'page' : undefined} {...rest}>
      {children}
    </NextLink>
  )
}

export function useNavigate() {
  const router = useRouter()
  return (to: string | number, options?: { replace?: boolean }) => {
    if (typeof to === 'number') {
      if (to < 0) router.back()
      else router.forward()
      return
    }
    if (options?.replace) router.replace(to)
    else router.push(to)
  }
}

export function useParams<T extends Record<string, string>>(): Partial<T> {
  return (useNextParams() ?? {}) as Partial<T>
}

export function useLocation() {
  const pathname = usePathname()
  const searchParams = useNextSearchParams()
  const query = searchParams.toString()
  return { pathname, search: query ? `?${query}` : '', hash: '', state: null as unknown }
}

/** Reads the query string. Writing it replaces the URL in place, as the studio's search and filter controls expect. */
export function useSearchParams(): [URLSearchParams, (next: URLSearchParams | Record<string, string> | ((current: URLSearchParams) => URLSearchParams | Record<string, string>), options?: { replace?: boolean }) => void] {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useNextSearchParams()
  const current = new URLSearchParams(searchParams.toString())

  function setSearchParams(next: URLSearchParams | Record<string, string> | ((current: URLSearchParams) => URLSearchParams | Record<string, string>)) {
    const value = typeof next === 'function' ? next(new URLSearchParams(searchParams.toString())) : next
    const params = value instanceof URLSearchParams ? value : new URLSearchParams(value)
    const query = params.toString()
    router.replace(query ? `${pathname}?${query}` : pathname)
  }

  return [current, setSearchParams]
}

/** Sends the visitor somewhere else as soon as the component shows. */
export function Navigate({ to, replace = true }: { to: string; replace?: boolean; state?: unknown }) {
  const router = useRouter()
  useEffect(() => {
    if (replace) router.replace(to)
    else router.push(to)
  }, [router, replace, to])
  return null
}
