import type { Metadata } from 'next'
import type { ReactNode } from 'react'
import { StudioShell } from '@/components/StudioShell'

// The studio is for signed-in users only, so search engines are asked to leave it out.
export const metadata: Metadata = {
  robots: { index: false, follow: false },
}

export default function StudioLayout({ children }: { children: ReactNode }) {
  return <StudioShell>{children}</StudioShell>
}
