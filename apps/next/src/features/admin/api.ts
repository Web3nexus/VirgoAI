import { api } from '@/lib/api'
import type { AuthResponse } from '@/features/auth/types'
import type {
  PaymentsStatus,
  BillingAdmin,
  PurchaseRowAdmin,
  SubscriptionRowAdmin,
  TeamMember,
  AdminOverview,
  CredentialRow,
  DashboardRange,
  AdminUserDetail,
  AdminUserRow,
  AuditRow,
  EmailLogRow,
  EmailTemplateRow,
  EmailTemplatesResponse,
  Paginated,
  PricingResponse,
  PricingSyncResponse,
  ProviderRow,
  PersonalPromoConfig,
  SystemResponse,
  SystemSettings,
  UserStatusFilter,
} from './types'

export async function adminLogin(payload: { email: string; password: string }): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/admin/login', payload)
  return data
}

export async function fetchOverview(range: DashboardRange): Promise<AdminOverview> {
  const { data } = await api.get<AdminOverview>('/admin/overview', { params: { range } })
  return data
}

export async function fetchCredentials(): Promise<{ data: CredentialRow[] }> {
  const { data } = await api.get<{ data: CredentialRow[] }>('/admin/credentials')
  return data
}

export async function saveCredential(name: string, value: string): Promise<CredentialRow> {
  const { data } = await api.put<CredentialRow>(`/admin/credentials/${name}`, { value })
  return data
}

export async function removeCredential(name: string): Promise<CredentialRow> {
  const { data } = await api.delete<CredentialRow>(`/admin/credentials/${name}`)
  return data
}

export async function testCredential(name: string): Promise<{ ok: boolean; message: string }> {
  const { data } = await api.post<{ ok: boolean; message: string }>(`/admin/credentials/${name}/test`)
  return data
}

// Users

export async function fetchUsers(params: { q: string; page: number; status: UserStatusFilter }): Promise<Paginated<AdminUserRow>> {
  const { data } = await api.get<Paginated<AdminUserRow>>('/admin/users', { params })
  return data
}

export async function createUser(payload: { name: string; email: string; password: string; role: string }): Promise<AdminUserRow> {
  const { data } = await api.post<AdminUserRow>('/admin/users', payload)
  return data
}

export async function fetchUser(id: string): Promise<AdminUserDetail> {
  const { data } = await api.get<AdminUserDetail>(`/admin/users/${id}`)
  return data
}

export async function updateUser(
  id: number,
  patch: { name?: string; email?: string; role?: string; suspended?: boolean },
): Promise<AdminUserRow> {
  const { data } = await api.patch<AdminUserRow>(`/admin/users/${id}`, patch)
  return data
}

export async function deleteUser(id: number): Promise<void> {
  await api.delete(`/admin/users/${id}`)
}

export async function restoreUser(id: number): Promise<AdminUserRow> {
  const { data } = await api.post<AdminUserRow>(`/admin/users/${id}/restore`)
  return data
}

export async function adjustCredit(id: number, payload: { amount_usd: number; note: string }): Promise<AdminUserRow> {
  const { data } = await api.post<AdminUserRow>(`/admin/users/${id}/credits`, payload)
  return data
}

// Providers

export async function fetchProviders(): Promise<{ data: ProviderRow[] }> {
  const { data } = await api.get<{ data: ProviderRow[] }>('/admin/providers')
  return data
}

export async function updateProvider(key: string, patch: { status?: string; priority?: number; name?: string }): Promise<ProviderRow> {
  const { data } = await api.patch<ProviderRow>(`/admin/providers/${key}`, patch)
  return data
}

export async function updateProviderModel(key: string, modelId: number, patch: { enabled?: boolean; priority?: number }): Promise<ProviderRow> {
  const { data } = await api.patch<ProviderRow>(`/admin/providers/${key}/models/${modelId}`, patch)
  return data
}

export async function syncProvider(key: string): Promise<{ errors: string[]; provider: ProviderRow }> {
  const { data } = await api.post<{ errors: string[]; provider: ProviderRow }>(`/admin/providers/${key}/sync`)
  return data
}

// Pricing

export async function fetchPricing(): Promise<PricingResponse> {
  const { data } = await api.get<PricingResponse>('/admin/pricing')
  return data
}

export async function updateMargin(marginPercent: number): Promise<{ margin_percent: number; kept_share_percent: number }> {
  const { data } = await api.patch('/admin/pricing/margin', { margin_percent: marginPercent })
  return data
}

export async function updatePromo(promo: PersonalPromoConfig): Promise<PersonalPromoConfig> {
  const { data } = await api.patch<PersonalPromoConfig>('/admin/pricing/promo', promo)
  return data
}

export async function updatePrice(id: number, costUsd: number): Promise<void> {
  await api.patch(`/admin/pricing/prices/${id}`, { cost_usd: costUsd })
}

export async function syncPricing(): Promise<PricingSyncResponse> {
  const { data } = await api.post<PricingSyncResponse>('/admin/pricing/sync')
  return data
}

// Email

export async function fetchEmailTemplates(): Promise<EmailTemplatesResponse> {
  const { data } = await api.get<EmailTemplatesResponse>('/admin/email/templates')
  return data
}

export type TemplatePayload = { key?: string; name: string; subject: string; body: string; enabled: boolean }

export async function createEmailTemplate(payload: TemplatePayload & { key: string }): Promise<EmailTemplateRow> {
  const { data } = await api.post<EmailTemplateRow>('/admin/email/templates', payload)
  return data
}

export async function updateEmailTemplate(id: number, payload: TemplatePayload): Promise<EmailTemplateRow> {
  const { data } = await api.patch<EmailTemplateRow>(`/admin/email/templates/${id}`, payload)
  return data
}

export async function deleteEmailTemplate(id: number): Promise<void> {
  await api.delete(`/admin/email/templates/${id}`)
}

export async function testEmailTemplate(id: number): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>(`/admin/email/templates/${id}/test`)
  return data
}

export async function fetchEmailLogs(page: number): Promise<Paginated<EmailLogRow>> {
  const { data } = await api.get<Paginated<EmailLogRow>>('/admin/email/logs', { params: { page } })
  return data
}

// System

export async function fetchSystem(): Promise<SystemResponse> {
  const { data } = await api.get<SystemResponse>('/admin/system')
  return data
}

export async function updateSystem(patch: Partial<SystemSettings> & { mail_password?: string }): Promise<{ settings: SystemSettings }> {
  const { data } = await api.patch<{ settings: SystemSettings }>('/admin/system', patch)
  return data
}

// Audit

export async function fetchAudit(page: number): Promise<Paginated<AuditRow>> {
  const { data } = await api.get<Paginated<AuditRow>>('/admin/audit', { params: { page } })
  return data
}

// Billing (owner)

export async function fetchBillingAdmin(): Promise<BillingAdmin> {
  const { data } = await api.get<BillingAdmin>('/admin/billing')
  return data
}

export async function savePlan(id: number | null, payload: Record<string, unknown>): Promise<void> {
  if (id === null) await api.post('/admin/billing/plans', payload)
  else await api.patch(`/admin/billing/plans/${id}`, payload)
}

export async function deletePlan(id: number): Promise<void> {
  await api.delete(`/admin/billing/plans/${id}`)
}

export async function savePack(id: number | null, payload: Record<string, unknown>): Promise<void> {
  if (id === null) await api.post('/admin/billing/packs', payload)
  else await api.patch(`/admin/billing/packs/${id}`, payload)
}

export async function deletePack(id: number): Promise<void> {
  await api.delete(`/admin/billing/packs/${id}`)
}

export async function fetchPurchases(status: string): Promise<Paginated<PurchaseRowAdmin>> {
  const { data } = await api.get<Paginated<PurchaseRowAdmin>>('/admin/billing/purchases', { params: { status } })
  return data
}

export async function markPurchasePaid(id: string): Promise<void> {
  await api.post(`/admin/billing/purchases/${id}/paid`)
}

export async function markPurchaseFailed(id: string): Promise<void> {
  await api.post(`/admin/billing/purchases/${id}/failed`)
}

export async function fetchSubscriptions(): Promise<Paginated<SubscriptionRowAdmin>> {
  const { data } = await api.get<Paginated<SubscriptionRowAdmin>>('/admin/billing/subscriptions')
  return data
}

export async function cancelSubscription(id: string): Promise<void> {
  await api.post(`/admin/billing/subscriptions/${id}/cancel`)
}

// Admin management

export async function fetchTeam(): Promise<{ data: TeamMember[] }> {
  const { data } = await api.get<{ data: TeamMember[] }>('/admin/team')
  return data
}

export async function grantTeamAccess(email: string): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/admin/team', { email })
  return data
}

export async function revokeTeamAccess(id: number): Promise<void> {
  await api.delete(`/admin/team/${id}`)
}

// Email engine

export async function sendTestEmail(): Promise<{ ok: boolean; message: string }> {
  const { data } = await api.post<{ ok: boolean; message: string }>('/admin/system/test-email')
  return data
}

// Payments

export async function fetchPayments(): Promise<PaymentsStatus> {
  const { data } = await api.get<PaymentsStatus>('/admin/payments')
  return data
}

export async function createFlutterwavePlans(planId: number): Promise<{ monthly: number; yearly: number }> {
  const { data } = await api.post<{ monthly: number; yearly: number }>(`/admin/billing/plans/${planId}/flutterwave`)
  return data
}
