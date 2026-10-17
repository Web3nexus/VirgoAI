// Absolute URL of the app domain (virgoai app subdomain). Set VITE_APP_URL per environment.
const APP_URL = (import.meta.env.VITE_APP_URL ?? 'http://localhost:5173').replace(/\/$/, '')

export function appUrl(path: string): string {
  return `${APP_URL}${path}`
}
