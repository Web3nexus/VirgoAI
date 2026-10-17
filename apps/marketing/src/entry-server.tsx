import { renderToString } from 'react-dom/server'
import { LandingPage } from '@/pages/LandingPage'

/** Renders the landing page to HTML at build time, so crawlers and link previews see the content without running JavaScript. */
export function render(): string {
  return renderToString(<LandingPage />)
}
