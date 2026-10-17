<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Catalog;
use App\Billing\Checkout;
use App\Billing\Fulfilment;
use App\Billing\Money;
use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\CreditPack;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Plans, top-up packs, purchases, and subscriptions. Money is entered in dollars and stored in micro-dollars.
 */
class AdminBillingController extends Controller
{
    public function show(Catalog $catalog): JsonResponse
    {
        return response()->json([
            'economics' => $catalog->economics(),
            'plans' => Plan::query()->orderBy('sort')->get()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'key' => $plan->key,
                'name' => $plan->name,
                'tagline' => $plan->tagline,
                'monthly_usd' => Money::toUsd($plan->monthly_micros),
                'yearly_usd' => Money::toUsd($plan->yearly_micros),
                'monthly_credit_usd' => Money::toUsd($plan->monthly_credit_micros),
                'features' => $plan->features ?? [],
                'highlighted' => (bool) $plan->highlighted,
                'active' => (bool) $plan->active,
                'sort' => $plan->sort,
                'paddle_monthly_price_id' => $plan->paddle_monthly_price_id,
                'paddle_yearly_price_id' => $plan->paddle_yearly_price_id,
                'flutterwave_monthly_plan_id' => $plan->flutterwave_monthly_plan_id,
                'flutterwave_yearly_plan_id' => $plan->flutterwave_yearly_plan_id,
            ])->values(),
            'packs' => CreditPack::query()->orderBy('sort')->get()->map(fn (CreditPack $pack) => $this->packView($pack))->values(),
            'subscriptions' => [
                'active' => Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count(),
                'past_due' => Subscription::query()->where('status', Subscription::STATUS_PAST_DUE)->count(),
                'ended' => Subscription::query()->where('status', Subscription::STATUS_ENDED)->count(),
            ],
            'pending_purchases' => Purchase::query()->where('status', Purchase::STATUS_PENDING)->count(),
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $plan = Plan::create($this->planData($request));
        AdminAuditLog::record($request->user(), 'billing.plan_created', $plan, ['key' => $plan->key]);

        return response()->json(['id' => $plan->id], 201);
    }

    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $before = $plan->only(['monthly_micros', 'yearly_micros', 'monthly_credit_micros', 'active']);
        $plan->update($this->planData($request, $plan));

        AdminAuditLog::record($request->user(), 'billing.plan_updated', $plan, [
            'key' => $plan->key,
            'monthly_usd' => ['from' => Money::toUsd($before['monthly_micros']), 'to' => Money::toUsd($plan->monthly_micros)],
            'yearly_usd' => ['from' => Money::toUsd($before['yearly_micros']), 'to' => Money::toUsd($plan->yearly_micros)],
            'credit_usd' => ['from' => Money::toUsd($before['monthly_credit_micros']), 'to' => Money::toUsd($plan->monthly_credit_micros)],
        ]);

        return response()->json(['id' => $plan->id]);
    }

    public function destroyPlan(Request $request, Plan $plan): JsonResponse
    {
        if (Subscription::query()->where('plan_key', $plan->key)->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])->exists()) {
            throw ValidationException::withMessages(['plan' => 'Subscribers are on this plan. Hide it instead of deleting it.']);
        }

        $key = $plan->key;
        $plan->delete();
        AdminAuditLog::record($request->user(), 'billing.plan_deleted', null, ['key' => $key]);

        return response()->json(['message' => 'Plan deleted.']);
    }

    public function storePack(Request $request): JsonResponse
    {
        $pack = CreditPack::create($this->packData($request));
        AdminAuditLog::record($request->user(), 'billing.pack_created', $pack, ['key' => $pack->key]);

        return response()->json($this->packView($pack), 201);
    }

    public function updatePack(Request $request, CreditPack $pack): JsonResponse
    {
        $pack->update($this->packData($request, $pack));
        AdminAuditLog::record($request->user(), 'billing.pack_updated', $pack, ['key' => $pack->key]);

        return response()->json($this->packView($pack->refresh()));
    }

    public function destroyPack(Request $request, CreditPack $pack): JsonResponse
    {
        $key = $pack->key;
        $pack->delete();
        AdminAuditLog::record($request->user(), 'billing.pack_deleted', null, ['key' => $key]);

        return response()->json(['message' => 'Top-up deleted.']);
    }

    public function purchases(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');

        $purchases = Purchase::query()
            ->with('user:id,email')
            ->when(in_array($status, ['pending', 'paid', 'failed'], true), fn ($query) => $query->where('status', $status))
            ->latest('created_at')
            ->paginate(25);

        return response()->json([
            'data' => $purchases->getCollection()->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'user' => $purchase->user?->email,
                'kind' => $purchase->kind,
                'item_key' => $purchase->item_key,
                'interval' => $purchase->interval,
                'amount_usd' => Money::toUsd($purchase->amount_micros),
                'credits_usd' => Money::toUsd($purchase->credits_micros),
                'status' => $purchase->status,
                'provider' => $purchase->provider,
                'paid_at' => $purchase->paid_at?->toIso8601String(),
                'created_at' => $purchase->created_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $purchases->currentPage(), 'last_page' => $purchases->lastPage()],
        ]);
    }

    /** Record a payment received outside the provider, such as a bank transfer. Grants the purchase. */
    public function markPaid(Request $request, Purchase $purchase, Fulfilment $fulfilment): JsonResponse
    {
        if ($purchase->status !== Purchase::STATUS_PENDING) {
            throw ValidationException::withMessages(['purchase' => 'Only a pending purchase can be marked paid.']);
        }

        $fulfilment->markPaid($purchase);
        AdminAuditLog::record($request->user(), 'billing.purchase_paid', $purchase, ['kind' => $purchase->kind, 'item' => $purchase->item_key]);

        return response()->json(['status' => $purchase->refresh()->status]);
    }

    public function markFailed(Request $request, Purchase $purchase, Fulfilment $fulfilment): JsonResponse
    {
        $fulfilment->markFailed($purchase);
        AdminAuditLog::record($request->user(), 'billing.purchase_failed', $purchase, ['kind' => $purchase->kind]);

        return response()->json(['status' => $purchase->refresh()->status]);
    }

    /** Start a plan for a user directly, recorded as a paid manual purchase. */
    public function grantPlan(Request $request, User $user, Checkout $checkout, Fulfilment $fulfilment): JsonResponse
    {
        $data = $request->validate([
            'plan_key' => ['required', 'string', Rule::exists('plans', 'key')],
            'interval' => ['required', 'string', Rule::in([Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY])],
        ]);

        try {
            $purchase = $checkout->plan($user, $data['plan_key'], $data['interval'], 'manual');
            $fulfilment->markPaid($purchase);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['plan_key' => $e->getMessage()]);
        }

        AdminAuditLog::record($request->user(), 'billing.plan_granted', $user, ['plan' => $data['plan_key'], 'interval' => $data['interval']]);

        return response()->json(['message' => 'Plan started for '.$user->email.'.']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $subs = Subscription::query()->with('user:id,email')->latest('created_at')->paginate(25);

        return response()->json([
            'data' => $subs->getCollection()->map(fn (Subscription $sub) => [
                'id' => $sub->id,
                'user' => $sub->user?->email,
                'plan_key' => $sub->plan_key,
                'interval' => $sub->interval,
                'status' => $sub->status,
                'entitled' => $sub->isEntitled(),
                'current_period_end' => $sub->current_period_end->toIso8601String(),
                'cancel_at_period_end' => $sub->cancel_at_period_end,
            ])->values(),
            'meta' => ['current_page' => $subs->currentPage(), 'last_page' => $subs->lastPage()],
        ]);
    }

    public function cancelSubscription(Request $request, Subscription $subscription, Subscriptions $subscriptions): JsonResponse
    {
        $subscriptions->cancel($subscription);
        AdminAuditLog::record($request->user(), 'billing.subscription_cancelled', $subscription, ['plan' => $subscription->plan_key]);

        return response()->json(['message' => 'The plan will end at the end of its period.']);
    }

    /** @return array<string, mixed> */
    private function planData(Request $request, ?Plan $existing = null): array
    {
        $data = $request->validate([
            'key' => [$existing ? 'sometimes' : 'required', 'string', 'max:32', 'regex:/^[a-z_]+$/', Rule::unique('plans', 'key')->ignore($existing?->id)],
            'name' => ['required', 'string', 'max:64'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'monthly_usd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'yearly_usd' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'monthly_credit_usd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'features' => ['present', 'array', 'max:12'],
            'features.*' => ['string', 'max:120'],
            'highlighted' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'between:0,100'],
            'paddle_monthly_price_id' => ['nullable', 'string', 'regex:/^pri_[a-z0-9]+$/'],
            'paddle_yearly_price_id' => ['nullable', 'string', 'regex:/^pri_[a-z0-9]+$/'],
        ]);

        if ($data['yearly_usd'] > $data['monthly_usd'] * 12) {
            throw ValidationException::withMessages(['yearly_usd' => 'A yearly price above twelve monthly payments makes no sense.']);
        }

        return [
            'key' => $data['key'] ?? $existing?->key,
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? null,
            'monthly_micros' => Money::fromUsd($data['monthly_usd']),
            'yearly_micros' => Money::fromUsd($data['yearly_usd']),
            'monthly_credit_micros' => Money::fromUsd($data['monthly_credit_usd']),
            'features' => array_values($data['features']),
            'highlighted' => $data['highlighted'],
            'active' => $data['active'],
            'sort' => $data['sort'],
            'paddle_monthly_price_id' => $data['paddle_monthly_price_id'] ?? null,
            'paddle_yearly_price_id' => $data['paddle_yearly_price_id'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function packData(Request $request, ?CreditPack $existing = null): array
    {
        $data = $request->validate([
            'key' => [$existing ? 'sometimes' : 'required', 'string', 'max:32', 'regex:/^[a-z_0-9]+$/', Rule::unique('credit_packs', 'key')->ignore($existing?->id)],
            'name' => ['required', 'string', 'max:64'],
            'credits_usd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'price_usd' => ['required', 'numeric', 'min:1', 'max:100000'],
            'subscriber_discount_percent' => ['required', 'integer', 'between:0,90'],
            'active' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'between:0,100'],
            'paddle_price_id' => ['nullable', 'string', 'regex:/^pri_[a-z0-9]+$/'],
            'paddle_subscriber_price_id' => ['nullable', 'string', 'regex:/^pri_[a-z0-9]+$/'],
        ]);

        return [
            'key' => $data['key'] ?? $existing?->key,
            'name' => $data['name'],
            'paddle_price_id' => $data['paddle_price_id'] ?? null,
            'paddle_subscriber_price_id' => $data['paddle_subscriber_price_id'] ?? null,
            'credits_micros' => Money::fromUsd($data['credits_usd']),
            'price_micros' => Money::fromUsd($data['price_usd']),
            'subscriber_discount_percent' => $data['subscriber_discount_percent'],
            'active' => $data['active'],
            'sort' => $data['sort'],
        ];
    }

    /** @return array<string, mixed> */
    private function packView(CreditPack $pack): array
    {
        return [
            'id' => $pack->id,
            'key' => $pack->key,
            'name' => $pack->name,
            'credits_usd' => Money::toUsd($pack->credits_micros),
            'price_usd' => Money::toUsd($pack->price_micros),
            'subscriber_discount_percent' => $pack->subscriber_discount_percent,
            'subscriber_price_usd' => Money::toUsd((int) round($pack->price_micros * (100 - $pack->subscriber_discount_percent) / 100)),
            'active' => (bool) $pack->active,
            'sort' => $pack->sort,
            'paddle_price_id' => $pack->paddle_price_id,
            'paddle_subscriber_price_id' => $pack->paddle_subscriber_price_id,
        ];
    }
}
