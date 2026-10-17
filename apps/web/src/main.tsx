import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, useLocation } from 'react-router-dom'
import './index.css'
import App from './App'
import { ErrorBoundary } from '@/components/errors/ErrorBoundary'
import { Toaster } from '@/components/ui/sonner'
import { queryClient } from '@/lib/query'

/** Keyed by path, so a crash on one page does not keep the next page on the error screen. */
function RoutedApp() {
  const location = useLocation()
  return (
    <ErrorBoundary key={location.pathname}>
      <App />
    </ErrorBoundary>
  )
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <RoutedApp />
        <Toaster />
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
)
