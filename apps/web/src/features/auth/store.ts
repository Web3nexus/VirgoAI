import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { AuthUser } from './types'

type AuthState = {
  user: AuthUser | null
  token: string | null
  setSession: (user: AuthUser, token: string) => void
  setUser: (user: AuthUser) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      setSession: (user, token) => set({ user, token }),
      setUser: (user) => set({ user }),
      logout: () => set({ user: null, token: null }),
    }),
    { name: 'virgoai.auth' },
  ),
)
