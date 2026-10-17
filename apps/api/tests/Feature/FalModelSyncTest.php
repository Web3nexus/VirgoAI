<?php

use App\Ai\Fal\FalEndpoints;
use App\Ai\Fal\FalModelSync;
use App\Enums\Capability;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;
use Illuminate\Support\Facades\Http;

/** A minimal OpenAPI document whose POST body exposes the given input fields. */
function falOpenApi(array $fields): array
{
    return [
        'paths' => [
            '/fal-ai/example' => [
                'post' => [
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Input']]]],
                ],
            ],
        ],
        'components' => [
            'schemas' => [
                'Input' => ['type' => 'object', 'properties' => array_fill_keys($fields, ['type' => 'string'])],
            ],
        ],
    ];
}

/** One item in fal's find-mode response, built from the fields this app sends. */
function falEndpointItem(string $endpointId, ?array $fields = null, string $status = 'active'): array
{
    $spec = FalEndpoints::all()[$endpointId];

    return [
        'endpoint_id' => $endpointId,
        'metadata' => [
            'display_name' => 'Display '.$endpointId,
            'category' => $spec['category'],
            'description' => 'desc',
            'status' => $status,
            'model_url' => 'https://fal.run/'.$endpointId,
        ],
        'openapi' => falOpenApi($fields ?? $spec['fields']),
    ];
}

/** Every endpoint this app calls, each valid, unless overridden. */
function falFixture(array $overrides = []): array
{
    $items = [];
    foreach (array_keys(FalEndpoints::all()) as $endpointId) {
        $items[$endpointId] = $overrides[$endpointId] ?? falEndpointItem($endpointId);
    }

    return array_values(array_filter($items, fn ($item) => $item !== null));
}

beforeEach(function () {
    config(['virgoai.credentials.FAL_KEY' => 'fal-test-key']);
});

it('registers the selectable endpoints it verified, and nothing else', function () {
    Http::fake(['api.fal.ai/v1/models*' => Http::response(['models' => falFixture(), 'has_more' => false])]);

    $summary = app(FalModelSync::class)->sync();

    expect($summary['provider'])->toBe('fal')
        ->and($summary['upserted'])->toBe(count(FalEndpoints::selectableIds()))
        ->and($summary['errors'])->toBe([]);

    $provider = AiProvider::where('key', 'fal')->firstOrFail();
    expect($provider->credentials_key)->toBe('FAL_KEY');

    $image = AiModel::where('ai_provider_id', $provider->id)->where('key', FalEndpoints::IMAGE_TEXT)->firstOrFail();
    expect($image->name)->toBe('Display fal-ai/gpt-image-1.5')
        ->and($image->enabled)->toBeTrue()
        ->and(ProviderCapability::where('ai_model_id', $image->id)->value('capability'))->toBe(Capability::Image);

    expect(AiModel::where('key', FalEndpoints::IMAGE_EDIT)->exists())->toBeFalse();
});

it('asks fal for exactly the endpoints it uses, with repeated endpoint_id parameters', function () {
    Http::fake(['api.fal.ai/v1/models*' => Http::response(['models' => falFixture()])]);

    app(FalModelSync::class)->sync();

    Http::assertSent(function ($request) {
        $url = (string) $request->url();

        return str_contains($url, 'endpoint_id=fal-ai%2Fgpt-image-1.5&')
            && str_contains($url, 'endpoint_id=fal-ai%2Fgpt-image-1.5%2Fedit')
            && str_contains($url, 'endpoint_id=bytedance%2Fseedance-2.0%2Fimage-to-video')
            && str_contains($url, 'expand=openapi-3.0')
            && str_contains((string) $request->header('Authorization')[0], 'fal-test-key');
    });
});

it('disables and reports an endpoint whose schema lost a field this app sends', function () {
    $fields = array_values(array_diff(FalEndpoints::all()[FalEndpoints::VIDEO_IMAGE]['fields'], ['generate_audio']));

    Http::fake(['api.fal.ai/v1/models*' => Http::sequence()
        ->push(['models' => falFixture()])
        ->push(['models' => falFixture([FalEndpoints::VIDEO_IMAGE => falEndpointItem(FalEndpoints::VIDEO_IMAGE, $fields)])]),
    ]);

    app(FalModelSync::class)->sync();
    $summary = app(FalModelSync::class)->sync();

    expect($summary['errors'])->toHaveCount(1)
        ->and($summary['errors'][0])->toContain('generate_audio');

    $provider = AiProvider::where('key', 'fal')->firstOrFail();
    expect(AiModel::where('ai_provider_id', $provider->id)->where('key', FalEndpoints::VIDEO_IMAGE)->value('enabled'))->toBeFalse();
});

it('reports an endpoint that is no longer active', function () {
    Http::fake(['api.fal.ai/v1/models*' => Http::response([
        'models' => falFixture([FalEndpoints::IMAGE_TEXT => falEndpointItem(FalEndpoints::IMAGE_TEXT, null, 'deprecated')]),
    ])]);

    $summary = app(FalModelSync::class)->sync();

    expect($summary['upserted'])->toBe(count(FalEndpoints::selectableIds()) - 1)
        ->and($summary['errors'][0])->toContain('not active');
});

it('reports an endpoint that fal did not return', function () {
    $items = array_values(array_filter(falFixture(), fn ($item) => $item['endpoint_id'] !== FalEndpoints::VIDEO_IMAGE));

    Http::fake(['api.fal.ai/v1/models*' => Http::response(['models' => $items])]);

    $summary = app(FalModelSync::class)->sync();

    expect($summary['errors'][0])->toContain('did not return');
});

it('refuses to sync without a fal key', function () {
    config(['virgoai.credentials.FAL_KEY' => null]);
    putenv('FAL_KEY');
    Http::fake();

    expect(fn () => app(FalModelSync::class)->sync())
        ->toThrow(RuntimeException::class, 'FAL_KEY is not configured');
    Http::assertNothingSent();
});

it('fails the sync command when a verification fails', function () {
    Http::fake(['api.fal.ai/v1/models*' => Http::response(['models' => []])]);

    $this->artisan('ai:sync')->assertExitCode(1);
});
