'use client'

import type { ReactNode } from 'react'
import { SecureGatePage } from '@/views/admin/SecureGatePage'

export default function SecureGateLayout({ children }: { children: ReactNode }) {
  return <SecureGatePage>{children}</SecureGatePage>
}
