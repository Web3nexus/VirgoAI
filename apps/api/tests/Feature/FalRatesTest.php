<?php

use App\Ai\Fal\FalEndpoints;
use App\Billing\Catalog;
use App\Billing\FalRates;
use App\Billing\PriceLabel;
use App\Billing\PriceQuoter;
use App\Billing\PriceUnavailable;
use App\Billing\RateCard;
use App\Enums\Capability;
use App\Models\ModelPrice;

it('prices GPT Image at the size and quality it is sent at, not at the cheapest size', function () {
    // Landscape at medium quality is $0.050 on fal's page. A flat price would have charged $0.034.
    $quote = app(PriceQuoter::class)->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, 'a fox', ['size' => 'landscape_16_9', 'quality' => 'medium'], []);

    expect($quote->costMicros)->toBe(50_000);

    // With no quality asked for, the call is sent at high quality, so it is priced at high.
    $square = app(PriceQuoter::class)->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, 'a fox', ['size' => 'square_1_1'], []);
    expect($square->costMicros)->toBe(133_000);
});

it('prices Seedance at 1080p at its own rate, not the 720p rate', function () {
    $fifteenSeconds720 = app(PriceQuoter::class)->quote('fal', FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, 'a shot', ['duration' => 15, 'resolution' => '720p'], []);
    $fifteenSeconds1080 = app(PriceQuoter::class)->quote('fal', FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, 'a shot', ['duration' => 15, 'resolution' => '1080p'], []);

    expect($fifteenSeconds720->costMicros)->toBe(4_551_000)
        ->and($fifteenSeconds1080->costMicros)->toBe(10_230_000)
        ->and($fifteenSeconds1080->costMicros)->toBeGreaterThan($fifteenSeconds720->costMicros * 2);
});

it('bills Veo and Hailuo for the seconds the provider bills, not the seconds asked for', function () {
    // A five-second request is billed as six seconds by Veo, and as six by Hailuo.
    $veo = FalRates::quote(FalEndpoints::VIDEO_VEO_31_FAST, Capability::Video, ['duration' => 5, 'resolution' => '720p']);
    $hailuo = FalRates::quote(FalEndpoints::VIDEO_HAILUO, Capability::Video, ['duration' => 5, 'resolution' => '720p']);

    expect($veo['quantity'])->toBe(6)
        ->and($veo['cost_micros'])->toBe(900_000)
        ->and($hailuo['quantity'])->toBe(6)
        ->and($hailuo['cost_micros'])->toBe(270_000);
});

it('bills Kling with audio on, as the app sends it', function () {
    $kling = FalRates::quote(FalEndpoints::VIDEO_KLING, Capability::Video, ['duration' => 5, 'resolution' => '720p']);

    expect($kling['cost_micros'])->toBe(630_000);
});

it('refuses a size, quality or resolution the rate table does not price', function () {
    expect(fn () => FalRates::quote(FalEndpoints::IMAGE_TEXT, Capability::Image, ['size' => 'landscape_16_9', 'quality' => 'ultra']))
        ->toThrow(PriceUnavailable::class, 'does not price');

    expect(fn () => FalRates::quote(FalEndpoints::VIDEO_SEEDANCE_REF, Capability::Video, ['duration' => 5, 'resolution' => '4k']))
        ->toThrow(PriceUnavailable::class, 'does not price');
});

it('refuses 1080p video for a model with only a synced price, rather than charging its 720p price', function () {
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => FalEndpoints::VIDEO_SEEDANCE_FAST, 'unit' => 'second', 'cost_micros' => 200_000, 'source' => 'fal_api']);

    expect(fn () => app(PriceQuoter::class)->quote('fal', FalEndpoints::VIDEO_SEEDANCE_FAST, Capability::Video, 'a shot', ['duration' => 5, 'resolution' => '1080p'], []))
        ->toThrow(PriceUnavailable::class, '1080p');
});

it('lists every rate-table tier on the rate card, and marks the ones not yet checked', function () {
    $card = app(RateCard::class)->owner();
    $rows = collect($card['models'])->where('model', FalEndpoints::VIDEO_SEEDANCE_REF)->values();

    expect($rows->pluck('unit')->all())->toBe(['credits per second · 720p', 'credits per second · 1080p'])
        ->and($rows->first()['verified'])->toBeFalse()
        ->and(collect($card['models'])->where('model', 'fal-ai/gpt-image-1.5')->count())->toBe(9);
});

it('labels an image with the range it can cost, and sells plans at the dearest image', function () {
    $label = app(PriceLabel::class)->for('fal', FalEndpoints::IMAGE_TEXT, Capability::Image);

    expect($label)->toContain('per image')->toContain('to');

    $catalog = app(Catalog::class)->public();
    expect($catalog)->toHaveKey('plans');
    expect(app(RateCard::class)->verify())->toBe([]);
});
