// The app and the landing page share one origin, so links default to relative paths.
// Set NEXT_PUBLIC_APP_URL only when the studio lives on another domain.
const APP_URL = (process.env.NEXT_PUBLIC_APP_URL ?? '').replace(/\/$/, '')

export function appUrl(path: string): string {
  return `${APP_URL}${path}`
}
