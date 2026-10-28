<?php

use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Billing\FalRates;
use App\Enums\Capability;

it('sends keyframes at high quality unless a quality is asked for', function () {
    $request = new GenerationRequest(capability: Capability::Image, modelKey: FalEndpoints::IMAGE_TEXT, prompt: 'a lighthouse', options: ['size' => 'vertical_9_16']);

    expect(FalEndpoints::payload(FalEndpoints::IMAGE_TEXT, $request)['quality'])->toBe('high');
});

it('prices a keyframe at the same quality it is sent at', function () {
    $rate = FalRates::quote(FalEndpoints::IMAGE_TEXT, Capability::Image, ['size' => 'vertical_9_16']);

    expect($rate['cost_micros'])->toBe(200_000);
});
