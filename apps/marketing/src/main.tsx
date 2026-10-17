import { StrictMode } from 'react'
import { createRoot, hydrateRoot } from 'react-dom/client'
import './index.css'
import { LandingPage } from '@/pages/LandingPage'

const root = document.getElementById('root')!
const page = (
  <StrictMode>
    <LandingPage />
  </StrictMode>
)

// The build puts the rendered page into #root, so the client takes over that markup instead of replacing it.
if (root.hasChildNodes()) {
  hydrateRoot(root, page)
} else {
  createRoot(root).render(page)
}
