<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Money;
use App\Billing\PriceSync;
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
    public function show(PricingSettings $settings): JsonResponse
    {
        return response()->json([
            'margin_percent' => $settings->marginPercent(),
            'kept_share_percent' => $settings->keptShare(),
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
            'margin_percent' => ['required', 'integer', 'between:0,500'],
        ]);

        $before = $settings->marginPercent();
        AppSetting::write(PricingSettings::MARGIN_KEY, $data['margin_percent']);
        AdminAuditLog::record($request->user(), 'pricing.margin', null, ['from' => $before, 'to' => $data['margin_percent']]);

        return response()->json(['margin_percent' => $settings->marginPercent(), 'kept_share_percent' => $settings->keptShare()]);
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
