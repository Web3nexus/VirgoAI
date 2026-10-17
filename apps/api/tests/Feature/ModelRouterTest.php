<?php

use App\Ai\AdapterRegistry;
use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Ai\Exceptions\NoSuitableModel;
use App\Ai\ModelRouter;
use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;

class UnconfiguredAdapter implements ProviderAdapter
{
    public function key(): string
    {
        return 'unconfigured';
    }

    public function capabilities(): array
    {
        return Capability::cases();
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        return GenerationResult::failure('not configured', $this->key(), $request->modelKey);
    }
}

function echoProvider(): AiProvider
{
    return AiProvider::create([
        'key' => 'echo',
        'name' => 'Echo',
        'status' => ProviderStatus::Active,
        'priority' => 100,
    ]);
}

function modelFor(AiProvider $provider, string $key, int $priority, array $capabilities): AiModel
{
    $model = AiModel::create([
        'ai_provider_id' => $provider->id,
        'key' => $key,
        'name' => $key,
        'enabled' => true,
        'priority' => $priority,
    ]);

    foreach ($capabilities as $capability) {
        ProviderCapability::create([
            'ai_model_id' => $model->id,
            'capability' => $capability->value,
        ]);
    }

    return $model;
}

it('routes a capability to a configured model', function () {
    $provider = echoProvider();
    $model = modelFor($provider, 'echo-1', 10, [Capability::Text]);

    $routed = app(ModelRouter::class)->route(Capability::Text);

    expect($routed->modelKey())->toBe('echo-1')
        ->and($routed->providerKey())->toBe('echo')
        ->and($routed->model->id)->toBe($model->id);
});

it('returns an adapter for a routed model', function () {
    $provider = echoProvider();
    modelFor($provider, 'echo-1', 10, [Capability::Text]);

    $router = app(ModelRouter::class);
    $adapter = $router->adapterFor($router->route(Capability::Text));

    expect($adapter->key())->toBe('echo')
        ->and($adapter->isConfigured())->toBeTrue();
});

it('orders candidates by model priority', function () {
    $provider = echoProvider();
    modelFor($provider, 'late', 90, [Capability::Text]);
    modelFor($provider, 'early', 5, [Capability::Text]);

    $candidates = app(ModelRouter::class)->candidates(Capability::Text);

    expect($candidates->pluck('key')->all())->toBe(['early', 'late']);
});

it('ignores capabilities the model does not support', function () {
    $provider = echoProvider();
    modelFor($provider, 'text-only', 10, [Capability::Text]);

    expect(fn () => app(ModelRouter::class)->route(Capability::Image))
        ->toThrow(NoSuitableModel::class);
});

it('ignores providers whose status is not active', function () {
    $provider = echoProvider();
    modelFor($provider, 'echo-1', 10, [Capability::Text]);
    $provider->update(['status' => ProviderStatus::Disabled]);

    expect(fn () => app(ModelRouter::class)->route(Capability::Text))
        ->toThrow(NoSuitableModel::class);
});

it('ignores providers whose adapter is not configured', function () {
    app(AdapterRegistry::class)->register('unconfigured', UnconfiguredAdapter::class);

    $provider = AiProvider::create([
        'key' => 'unconfigured',
        'name' => 'Unconfigured',
        'status' => ProviderStatus::Active,
    ]);
    modelFor($provider, 'some-model', 10, [Capability::Text]);

    expect(fn () => app(ModelRouter::class)->route(Capability::Text))
        ->toThrow(NoSuitableModel::class);
});

it('produces a deterministic result from the echo adapter', function () {
    $provider = echoProvider();
    modelFor($provider, 'echo-1', 10, [Capability::Text]);

    $router = app(ModelRouter::class);
    $routed = $router->route(Capability::Text);

    $result = $router->adapterFor($routed)->generate(new GenerationRequest(
        capability: Capability::Text,
        modelKey: $routed->modelKey(),
        prompt: 'hello world again',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->output['echo'])->toBe('hello world again')
        ->and($result->costUsd)->toBe(0.0)
        ->and($result->providerKey)->toBe('echo');
});

it('reports whether a provider is configured via its credentials key', function () {
    $configured = AiProvider::create(['key' => 'a', 'name' => 'A', 'credentials_key' => null]);
    $needsKey = AiProvider::create([
        'key' => 'b',
        'name' => 'B',
        'credentials_key' => 'DEFINITELY_MISSING_ENV_VAR',
    ]);

    expect($configured->isConfigured())->toBeFalse()
        ->and($needsKey->isConfigured())->toBeFalse();
});

it('lists registered adapter keys', function () {
    expect(app(AdapterRegistry::class)->keys())->toContain('echo');
});
