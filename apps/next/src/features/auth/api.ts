import { api } from '@/lib/api'
import type { AuthResponse, AuthUser } from './types'

export type LoginPayload = {
  email: string
  password: string
}

export type RegisterPayload = {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/login', payload)
  return data
}

export async function register(payload: RegisterPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/register', payload)
  return data
}

export async function fetchMe(): Promise<AuthUser> {
  const { data } = await api.get<{ data: AuthUser }>('/user')
  return data.data
}

export async function logout(): Promise<void> {
  await api.post('/logout')
}
