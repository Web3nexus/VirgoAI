export type AuthUser = {
  id: number
  name: string
  email: string
  role?: string
  is_super_admin?: boolean
  balance_usd?: number
  balance_tokens?: number
  country_code?: string | null
  created_at?: string
}

export type AuthResponse = {
  user: AuthUser
  token: string
}
