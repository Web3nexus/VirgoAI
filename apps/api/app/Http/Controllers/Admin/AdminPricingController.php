<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Money;
use App\Billing\PriceSync;
use App\Billing\PersonalPromo;
use App\Billing\PricingSettings;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\ModelPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AdminPricingController extends Controller
{
    public function show(PricingSettings $settings, PersonalPromo $promo): JsonResponse
    {
        return response()->json([
            'margin_percent' => $settings->marginPercent(),
            'kept_share_percent' => $settings->keptShare(),
            'promo' => $promo->config(),
            'prices' => ModelPrice::query()->orderBy('provider_key')->orderBy('model_key')->orderBy('unit')->get()
                ->map(fn (ModelPrice $price) => [
                    'id' => $price->id,
                    'provider_key' => $price->provider_key,
                    'model_key' => $price->model_key,
                    'unit' => $price->unit,
                    'cost_usd' => Money::toUsd($price->cost_micros),
                    'sell_usd' => Money::toUsd($settings->sell($price->cost_micros)),
                    'source' => $price->source,
                    'verified_at' => $price->verified_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    public function updateMargin(Request $request, PricingSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'margin_percent' => ['required', 'integer', 'between:'.(int) config('pricing.min_margin_percent', 40).',500'],
        ]);

        $before = $settings->marginPercent();
        AppSetting::write(PricingSettings::MARGIN_KEY, $data['margin_percent']);
        AdminAuditLog::record($request->user(), 'pricing.margin', null, ['from' => $before, 'to' => $data['margin_percent']]);

        return response()->json(['margin_percent' => $settings->marginPercent(), 'kept_share_percent' => $settings->keptShare()]);
    }

    /** The personal discount for new accounts. Turning it off hides it at once; nothing is deleted. */
    public function updatePromo(Request $request, PersonalPromo $promo): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'percent' => ['required', 'integer', 'between:1,90'],
            'window_hours' => ['required', 'integer', 'between:1,168'],
        ]);

        $before = $promo->config();
        AppSetting::write(PersonalPromo::SETTING_KEY, $data);
        AdminAuditLog::record($request->user(), 'pricing.promo', null, ['from' => $before, 'to' => $data]);

        return response()->json($promo->config());
    }

    /** Set a price by hand. It is marked `admin` so no sync overwrites it. */
    public function updatePrice(Request $request, ModelPrice $price, PricingSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'cost_usd' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        $before = $price->cost_micros;
        $price->forceFill([
            'cost_micros' => Money::fromUsd($data['cost_usd']),
            'source' => ModelPrice::SOURCE_ADMIN,
            'verified_at' => now(),
        ])->save();

        AdminAuditLog::record($request->user(), 'pricing.price', $price, [
            'model' => $price->model_key,
            'unit' => $price->unit,
            'from_usd' => Money::toUsd($before),
            'to_usd' => Money::toUsd($price->cost_micros),
        ]);

        return response()->json([
            'id' => $price->id,
            'cost_usd' => Money::toUsd($price->cost_micros),
            'sell_usd' => Money::toUsd($settings->sell($price->cost_micros)),
            'source' => $price->source,
        ]);
    }

    /** Re-read fal's prices and re-seed Anthropic's reference prices. */
    public function sync(Request $request, PriceSync $sync): JsonResponse
    {
        $errors = [];
        $result = ['fal' => null, 'anthropic' => $sync->seedAnthropic()];

        try {
            $result['fal'] = $sync->syncFal();
            $errors = array_merge($errors, $result['fal']['errors']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        AdminAuditLog::record($request->user(), 'pricing.sync', null, ['errors' => $errors]);

        return response()->json([...$result, 'errors' => $errors]);
    }
}
