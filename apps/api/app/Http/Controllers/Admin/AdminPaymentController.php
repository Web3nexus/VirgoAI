<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Money;
use App\Billing\Payments\FlutterwaveGateway;
use App\Billing\Payments\PaymentNotConfigured;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Settings\ProviderCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Payment provider status, the webhook addresses to paste into each provider, and the one-time setup that
 * creates a plan's recurring schedule on Flutterwave.
 */
class AdminPaymentController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $base = rtrim((string) config('app.url'), '/').'/api/v1/webhooks';

        return response()->json([
            'credentials' => collect(ProviderCredentials::PAYMENT_NAMES)->map(fn (string $name) => ProviderCredentials::describe($name))->values(),
            'webhooks' => [
                'flutterwave' => ['url' => $base.'/flutterwave', 'secret_name' => 'FLUTTERWAVE_SECRET_HASH'],
                'paddle' => ['url' => $base.'/paddle', 'secret_name' => 'PADDLE_WEBHOOK_SECRET'],
            ],
            'paddle_base' => config('virgoai.billing.paddle.base'),
            'recent_events' => PaymentEvent::query()->latest('id')->limit(15)->get()->map(fn (PaymentEvent $event) => [
                'provider' => $event->provider,
                'type' => $event->type,
                'outcome' => $event->outcome,
                'at' => $event->created_at?->toIso8601String(),
            ])->values(),
            'plans' => Plan::query()->orderBy('sort')->get()->map(fn (Plan $plan) => [
                'key' => $plan->key,
                'flutterwave' => $plan->flutterwave_monthly_plan_id !== null && $plan->flutterwave_yearly_plan_id !== null,
                'paddle' => $plan->paddle_monthly_price_id !== null && $plan->paddle_yearly_price_id !== null,
            ])->values(),
        ]);
    }

    /**
     * Create the monthly and yearly recurring plans on Flutterwave for one plan. Runs once: an existing id is kept.
     */
    public function createFlutterwavePlans(Request $request, Plan $plan, FlutterwaveGateway $flutterwave): JsonResponse
    {
        try {
            if ($plan->flutterwave_monthly_plan_id === null) {
                $plan->flutterwave_monthly_plan_id = $flutterwave->createPaymentPlan(
                    "virgoAi {$plan->name} monthly",
                    $plan->monthly_micros,
                    'monthly',
                    (int) config('virgoai.billing.flutterwave.plan_duration_monthly'),
                );
            }

            if ($plan->flutterwave_yearly_plan_id === null) {
                $plan->flutterwave_yearly_plan_id = $flutterwave->createPaymentPlan(
                    "virgoAi {$plan->name} yearly",
                    $plan->yearly_micros,
                    'yearly',
                    (int) config('virgoai.billing.flutterwave.plan_duration_yearly'),
                );
            }
        } catch (PaymentNotConfigured|RuntimeException $e) {
            throw ValidationException::withMessages(['plan' => $e->getMessage()]);
        }

        $plan->save();
        AdminAuditLog::record($request->user(), 'billing.flutterwave_plans', $plan, [
            'monthly' => $plan->flutterwave_monthly_plan_id,
            'yearly' => $plan->flutterwave_yearly_plan_id,
            'amount_usd' => Money::toUsd($plan->monthly_micros),
        ]);

        return response()->json(['monthly' => $plan->flutterwave_monthly_plan_id, 'yearly' => $plan->flutterwave_yearly_plan_id]);
    }
}
