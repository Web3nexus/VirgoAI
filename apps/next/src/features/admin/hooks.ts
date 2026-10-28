import { useMutation, useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as adminApi from './api'
import type { DashboardRange, UserStatusFilter } from './types'

/** Every mutation refreshes the owner console. Lists and details re-read on the next view. */
function useAdminMutation<TVariables, TResult>(
  fn: (variables: TVariables) => Promise<TResult>,
  message: string,
  invalidate: QueryKey[] = [['admin']],
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: fn,
    onSuccess: () => {
      toast.success(message)
      invalidate.forEach((key) => queryClient.invalidateQueries({ queryKey: key }))
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useAdminOverview(range: DashboardRange) {
  return useQuery({
    queryKey: ['admin', 'overview', range],
    queryFn: () => adminApi.fetchOverview(range),
    placeholderData: (previous) => previous,
  })
}

// API keys

export function useCredentials() {
  return useQuery({ queryKey: ['admin', 'credentials'], queryFn: adminApi.fetchCredentials })
}

export function useSaveCredential() {
  return useAdminMutation(
    ({ name, value }: { name: string; value: string }) => adminApi.saveCredential(name, value),
    'Key saved',
    [['admin', 'credentials'], ['admin', 'providers']],
  )
}

export function useRemoveCredential() {
  return useAdminMutation(
    (name: string) => adminApi.removeCredential(name),
    'Saved key removed',
    [['admin', 'credentials'], ['admin', 'providers']],
  )
}

export function useTestCredential() {
  return useMutation({
    mutationFn: (name: string) => adminApi.testCredential(name),
    onSuccess: (result) => (result.ok ? toast.success(result.message) : toast.error(result.message)),
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

// Users

export function useAdminUsers(q: string, page: number, status: UserStatusFilter) {
  return useQuery({
    queryKey: ['admin', 'users', q, page, status],
    queryFn: () => adminApi.fetchUsers({ q, page, status }),
    placeholderData: (previous) => previous,
  })
}

export function useAdminUser(id: string) {
  return useQuery({ queryKey: ['admin', 'user', id], queryFn: () => adminApi.fetchUser(id) })
}

export function useCreateUser() {
  return useAdminMutation(adminApi.createUser, 'User created', [['admin', 'users'], ['admin', 'overview']])
}

export function useUpdateUser(id: string) {
  return useAdminMutation(
    (patch: Parameters<typeof adminApi.updateUser>[1]) => adminApi.updateUser(Number(id), patch),
    'Saved',
    [['admin', 'user', id], ['admin', 'users']],
  )
}

export function useDeleteUser() {
  return useAdminMutation((id: number) => adminApi.deleteUser(id), 'User deleted', [['admin', 'users']])
}

export function useRestoreUser() {
  return useAdminMutation((id: number) => adminApi.restoreUser(id), 'User restored', [['admin', 'users']])
}

export function useAdjustCredit(id: string) {
  return useAdminMutation(
    (payload: { amount_usd: number; note: string }) => adminApi.adjustCredit(Number(id), payload),
    'Credit updated',
    [['admin', 'user', id], ['admin', 'users'], ['admin', 'overview']],
  )
}

// Providers

export function useAdminProviders() {
  return useQuery({ queryKey: ['admin', 'providers'], queryFn: adminApi.fetchProviders })
}

export function useUpdateProvider() {
  return useAdminMutation(
    ({ key, patch }: { key: string; patch: Parameters<typeof adminApi.updateProvider>[1] }) => adminApi.updateProvider(key, patch),
    'Provider saved',
    [['admin', 'providers']],
  )
}

export function useUpdateProviderModel() {
  return useAdminMutation(
    ({ key, modelId, patch }: { key: string; modelId: number; patch: { enabled?: boolean; priority?: number } }) =>
      adminApi.updateProviderModel(key, modelId, patch),
    'Model saved',
    [['admin', 'providers']],
  )
}

export function useSyncProvider() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (key: string) => adminApi.syncProvider(key),
    onSuccess: (result) => {
      if (result.errors.length > 0) toast.error(result.errors[0])
      else toast.success('Provider synced')
      queryClient.invalidateQueries({ queryKey: ['admin', 'providers'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'pricing'] })
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

// Pricing

export function useAdminPricing() {
  return useQuery({ queryKey: ['admin', 'pricing'], queryFn: adminApi.fetchPricing })
}

export function useUpdateMargin() {
  return useAdminMutation(adminApi.updateMargin, 'Margin saved')
}

export function useUpdatePromo() {
  return useAdminMutation(adminApi.updatePromo, 'Welcome offer saved', [['admin', 'pricing']])
}

export function useUpdatePrice() {
  return useAdminMutation(
    ({ id, costUsd }: { id: number; costUsd: number }) => adminApi.updatePrice(id, costUsd),
    'Price saved',
    [['admin', 'pricing'], ['admin', 'overview']],
  )
}

export function useSyncPricing() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: adminApi.syncPricing,
    onSuccess: (result) => {
      if (result.errors.length > 0) toast.error(result.errors[0])
      else toast.success('Prices refreshed')
      queryClient.invalidateQueries({ queryKey: ['admin', 'pricing'] })
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

// Email

export function useEmailTemplates() {
  return useQuery({ queryKey: ['admin', 'email', 'templates'], queryFn: adminApi.fetchEmailTemplates })
}

export function useCreateEmailTemplate() {
  return useAdminMutation(adminApi.createEmailTemplate, 'Template created', [['admin', 'email']])
}

export function useUpdateEmailTemplate() {
  return useAdminMutation(
    ({ id, payload }: { id: number; payload: adminApi.TemplatePayload }) => adminApi.updateEmailTemplate(id, payload),
    'Template saved',
    [['admin', 'email']],
  )
}

export function useDeleteEmailTemplate() {
  return useAdminMutation((id: number) => adminApi.deleteEmailTemplate(id), 'Template deleted', [['admin', 'email']])
}

export function useTestEmailTemplate() {
  return useAdminMutation((id: number) => adminApi.testEmailTemplate(id), 'Test email queued', [['admin', 'email', 'logs']])
}

export function useEmailLogs(page: number) {
  return useQuery({
    queryKey: ['admin', 'email', 'logs', page],
    queryFn: () => adminApi.fetchEmailLogs(page),
    placeholderData: (previous) => previous,
  })
}

// System

export function useAdminSystem() {
  return useQuery({ queryKey: ['admin', 'system'], queryFn: adminApi.fetchSystem })
}

export function useUpdateSystem() {
  return useAdminMutation(adminApi.updateSystem, 'Settings saved', [['admin', 'system']])
}

// Audit

export function useAdminAudit(page: number) {
  return useQuery({
    queryKey: ['admin', 'audit', page],
    queryFn: () => adminApi.fetchAudit(page),
    placeholderData: (previous) => previous,
  })
}

// Billing (owner)

export function useBillingAdmin() {
  return useQuery({ queryKey: ['admin', 'billing'], queryFn: adminApi.fetchBillingAdmin })
}

export function useSavePlan() {
  return useAdminMutation(
    ({ id, payload }: { id: number | null; payload: Record<string, unknown> }) => adminApi.savePlan(id, payload),
    'Plan saved',
    [['admin', 'billing']],
  )
}

export function useDeletePlan() {
  return useAdminMutation((id: number) => adminApi.deletePlan(id), 'Plan deleted', [['admin', 'billing']])
}

export function useSavePack() {
  return useAdminMutation(
    ({ id, payload }: { id: number | null; payload: Record<string, unknown> }) => adminApi.savePack(id, payload),
    'Top-up saved',
    [['admin', 'billing']],
  )
}

export function useDeletePack() {
  return useAdminMutation((id: number) => adminApi.deletePack(id), 'Top-up deleted', [['admin', 'billing']])
}

export function usePurchases(status: string) {
  return useQuery({
    queryKey: ['admin', 'purchases', status],
    queryFn: () => adminApi.fetchPurchases(status),
    placeholderData: (previous) => previous,
  })
}

export function useMarkPurchasePaid() {
  return useAdminMutation((id: string) => adminApi.markPurchasePaid(id), 'Payment recorded and credit granted', [['admin', 'purchases'], ['admin', 'billing'], ['admin', 'overview']])
}

export function useMarkPurchaseFailed() {
  return useAdminMutation((id: string) => adminApi.markPurchaseFailed(id), 'Marked as failed', [['admin', 'purchases'], ['admin', 'billing']])
}

export function useSubscriptions() {
  return useQuery({ queryKey: ['admin', 'subscriptions'], queryFn: adminApi.fetchSubscriptions })
}

export function useCancelSubscription() {
  return useAdminMutation((id: string) => adminApi.cancelSubscription(id), 'Plan will end at period end', [['admin', 'subscriptions'], ['admin', 'billing']])
}

// Admin management

export function useTeam() {
  return useQuery({ queryKey: ['admin', 'team'], queryFn: adminApi.fetchTeam })
}

export function useGrantTeamAccess() {
  return useAdminMutation((email: string) => adminApi.grantTeamAccess(email), 'Admin access granted', [['admin', 'team'], ['admin', 'users']])
}

export function useRevokeTeamAccess() {
  return useAdminMutation((id: number) => adminApi.revokeTeamAccess(id), 'Admin access removed', [['admin', 'team'], ['admin', 'users']])
}

// Email engine

export function useSendTestEmail() {
  return useMutation({
    mutationFn: adminApi.sendTestEmail,
    onSuccess: (result) => (result.ok ? toast.success(result.message) : toast.error(result.message)),
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

// Payments

export function usePaymentsStatus() {
  return useQuery({ queryKey: ['admin', 'payments'], queryFn: adminApi.fetchPayments })
}

export function useCreateFlutterwavePlans() {
  return useAdminMutation((planId: number) => adminApi.createFlutterwavePlans(planId), 'Flutterwave plans created', [['admin', 'billing'], ['admin', 'payments']])
}
