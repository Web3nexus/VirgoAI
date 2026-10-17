import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { Interval } from './types'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import * as billingApi from './api'

export function usePublicPricing() {
  return useQuery({ queryKey: ['pricing'], queryFn: billingApi.fetchPublicPricing, staleTime: 5 * 60_000 })
}

export function useBilling() {
  return useQuery({ queryKey: ['billing'], queryFn: billingApi.fetchBilling })
}

/** Opens the provider's hosted checkout. The server returns the same link for a repeated request. */
function openCheckout(url: string) {
  window.location.assign(url)
}

export function useCheckoutPlan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ planKey, interval }: { planKey: string; interval: Interval }) => billingApi.checkoutPlan(planKey, interval),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['billing'] })
      openCheckout(result.checkout_url)
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useCheckoutPack() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ packKey }: { packKey: string }) => billingApi.checkoutPack(packKey),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['billing'] })
      openCheckout(result.checkout_url)
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useCancelPlan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: billingApi.cancelPlan,
    onSuccess: (result) => {
      toast.success(result.message)
      queryClient.invalidateQueries({ queryKey: ['billing'] })
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}

export function useSetCountry() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (countryCode: string) => billingApi.setCountry(countryCode),
    onSuccess: () => {
      toast.success('Country saved')
      queryClient.invalidateQueries({ queryKey: ['billing'] })
    },
    onError: (error) => toast.error(apiErrorMessage(error)),
  })
}
