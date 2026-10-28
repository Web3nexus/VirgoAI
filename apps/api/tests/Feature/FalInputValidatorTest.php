<?php

use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Ai\Fal\FalInputValidator;
use App\Enums\Capability;

function validatorRequest(string $modelKey, array $options = []): GenerationRequest
{
    return new GenerationRequest(capability: Capability::Video, modelKey: $modelKey, prompt: 'a brass compass on a wet table', options: $options);
}

it('accepts a request with no options for every endpoint', function (string $endpoint) {
    expect(FalInputValidator::problem($endpoint, validatorRequest($endpoint)))->toBeNull();
})->with([
    FalEndpoints::VIDEO_IMAGE,
    FalEndpoints::VIDEO_SEEDANCE_FAST,
    FalEndpoints::VIDEO_KLING,
    FalEndpoints::VIDEO_HAILUO,
    FalEndpoints::VIDEO_VEO_31_FAST,
    FalEndpoints::VIDEO_VEO_3_FAST,
]);

it('refuses a Veo duration it would otherwise round', function (int $seconds, bool $allowed) {
    $problem = FalInputValidator::problem(FalEndpoints::VIDEO_VEO_31_FAST, validatorRequest(FalEndpoints::VIDEO_VEO_31_FAST, ['duration' => $seconds]));

    expect($problem === null)->toBe($allowed);
})->with([
    [4, true],
    [5, false],
    [6, true],
    [7, false],
    [8, true],
    [9, false],
]);

it('refuses a Hailuo duration other than 6 or 10', function (int $seconds, bool $allowed) {
    $problem = FalInputValidator::problem(FalEndpoints::VIDEO_HAILUO, validatorRequest(FalEndpoints::VIDEO_HAILUO, ['duration' => $seconds]));

    expect($problem === null)->toBe($allowed);
})->with([
    [6, true],
    [7, false],
    [10, true],
    [12, false],
]);

it('keeps Seedance durations to auto or 4 to 15 seconds', function (mixed $duration, bool $allowed) {
    $problem = FalInputValidator::problem(FalEndpoints::VIDEO_SEEDANCE_FAST, validatorRequest(FalEndpoints::VIDEO_SEEDANCE_FAST, ['duration' => $duration]));

    expect($problem === null)->toBe($allowed);
})->with([
    ['auto', true],
    [4, true],
    [15, true],
    [3, false],
    [16, false],
    ['soon', false],
]);

it('refuses a resolution the builder would silently change', function (string $endpoint, string $resolution, bool $allowed) {
    $problem = FalInputValidator::problem($endpoint, validatorRequest($endpoint, ['resolution' => $resolution]));

    expect($problem === null)->toBe($allowed);
})->with([
    [FalEndpoints::VIDEO_HAILUO, '720p', true],
    [FalEndpoints::VIDEO_HAILUO, '1080p', false],
    [FalEndpoints::VIDEO_VEO_31_FAST, '1080p', true],
    [FalEndpoints::VIDEO_VEO_31_FAST, '480p', false],
]);

it('refuses a frame size that is not a project format', function () {
    $problem = FalInputValidator::problem(FalEndpoints::IMAGE_TEXT, new GenerationRequest(
        capability: Capability::Image,
        modelKey: FalEndpoints::IMAGE_TEXT,
        prompt: 'a lighthouse',
        options: ['size' => 'banner_3_1'],
    ));

    expect($problem)->toContain('supported frame');
});

it('refuses audio that is not a real on or off', function () {
    $problem = FalInputValidator::problem(FalEndpoints::VIDEO_KLING, validatorRequest(FalEndpoints::VIDEO_KLING, ['generate_audio' => 'false']));

    expect($problem)->toContain('Audio must be on or off');
});

it('leaves the speech-to-text endpoint alone', function () {
    expect(FalInputValidator::problem(FalEndpoints::TRANSCRIBE, validatorRequest(FalEndpoints::TRANSCRIBE, ['duration' => 'whatever'])))->toBeNull();
});

it('takes the image quality tiers fal lists and nothing else', function (string $quality, bool $allowed) {
    $problem = FalInputValidator::problem(FalEndpoints::IMAGE_TEXT, new GenerationRequest(
        capability: Capability::Image,
        modelKey: FalEndpoints::IMAGE_TEXT,
        prompt: 'a lighthouse',
        options: ['quality' => $quality],
    ));

    expect($problem === null)->toBe($allowed);
})->with([
    ['low', true],
    ['medium', true],
    ['high', true],
    ['ultra', false],
]);

it('takes the Seedance resolutions in fal\'s schema, and the fast tier only up to 720p', function (string $endpoint, string $resolution, bool $allowed) {
    $problem = FalInputValidator::problem($endpoint, validatorRequest($endpoint, ['resolution' => $resolution]));

    expect($problem === null)->toBe($allowed);
})->with([
    [FalEndpoints::VIDEO_IMAGE, '1080p', true],
    [FalEndpoints::VIDEO_IMAGE, '4k', true],
    [FalEndpoints::VIDEO_IMAGE, '2k', false],
    [FalEndpoints::VIDEO_SEEDANCE_FAST, '720p', true],
    [FalEndpoints::VIDEO_SEEDANCE_FAST, '1080p', false],
]);
