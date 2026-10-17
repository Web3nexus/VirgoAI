import { useNavigate } from '@/lib/router'
import { useMutation, useQuery } from '@tanstack/react-query'
import * as authApi from './api'
import { useAuthStore } from './store'

export function useLogin() {
  const setSession = useAuthStore((s) => s.setSession)
  const navigate = useNavigate()

  return useMutation({
    mutationFn: authApi.login,
    onSuccess: (data) => {
      setSession(data.user, data.token)
      navigate('/app')
    },
  })
}

export function useRegister() {
  const setSession = useAuthStore((s) => s.setSession)
  const navigate = useNavigate()

  return useMutation({
    mutationFn: authApi.register,
    onSuccess: (data) => {
      setSession(data.user, data.token)
      navigate('/app')
    },
  })
}

export function useLogout() {
  const logout = useAuthStore((s) => s.logout)
  const navigate = useNavigate()

  return () => {
    authApi.logout().catch(() => undefined)
    logout()
    navigate('/login')
  }
}

export function useCurrentUser() {
  const token = useAuthStore((s) => s.token)
  return useQuery({
    queryKey: ['auth', 'me'],
    queryFn: authApi.fetchMe,
    enabled: Boolean(token),
  })
}
