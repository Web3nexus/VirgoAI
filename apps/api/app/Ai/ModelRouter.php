<?php

namespace App\Ai;

use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Data\RoutedModel;
use App\Ai\Data\ToolModelOption;
use App\Ai\Exceptions\NoSuitableModel;
use App\Ai\Exceptions\ProviderNotConfigured;
use App\Billing\PriceLabel;
use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use Illuminate\Support\Collection;

/**
 * Chooses a usable model for a capability from the DB-backed registry, in priority order,
 * considering only providers whose adapter is registered AND whose credentials are present.
 */
class ModelRouter
{
    public function __construct(protected AdapterRegistry $adapters) {}

    /**
     * Ordered candidate models that can serve the capability right now.
     *
     * @return Collection<int, AiModel>
     */
    public function candidates(Capability $capability): Collection
    {
        return AiModel::query()
            ->with(['provider', 'capabilities'])
            ->where('enabled', true)
            ->whereHas('provider', fn ($query) => $query->where('status', ProviderStatus::Active->value))
            ->whereHas('capabilities', fn ($query) => $query->where('capability', $capability->value))
            ->orderBy('priority')
            ->get()
            ->filter(fn (AiModel $model) => $this->unusableReason($model, $capability) === null)
            ->values();
    }

    public function route(Capability $capability): RoutedModel
    {
        $model = $this->candidates($capability)->first();

        if ($model === null) {
            throw NoSuitableModel::forCapability($capability);
        }

        return new RoutedModel($model, $model->provider);
    }

    /**
     * Route to one model the user picked. Fails with a plain reason if that model cannot run now.
     */
    public function routeTo(Capability $capability, string $modelKey): RoutedModel
    {
        $model = AiModel::query()
            ->with(['provider', 'capabilities'])
            ->where('key', $modelKey)
            ->first();

        if ($model === null || ! $model->capabilities->contains('capability', $capability->value)) {
            throw NoSuitableModel::forModel($modelKey, $capability, 'the model does not support this capability');
        }

        $reason = $this->unusableReason($model, $capability);
        if ($reason !== null) {
            throw NoSuitableModel::forModel($modelKey, $capability, $reason);
        }

        return new RoutedModel($model, $model->provider);
    }

    /**
     * Every model registered for a capability, with whether it can run right now and why not.
     *
     * @return list<ToolModelOption>
     */
    public function describe(Capability $capability): array
    {
        $labels = app(PriceLabel::class);

        return AiModel::query()
            ->with(['provider', 'capabilities'])
            ->where('enabled', true)
            ->whereHas('capabilities', fn ($query) => $query->where('capability', $capability->value))
            ->orderBy('priority')
            ->get()
            ->map(fn (AiModel $model) => new ToolModelOption(
                key: $model->key,
                name: $model->name,
                providerKey: $model->provider->key,
                usable: $this->unusableReason($model, $capability) === null,
                reason: $this->unusableReason($model, $capability),
                priceLabel: $labels->for($model->provider->key, $model->key, $capability),
            ))
            ->values()
            ->all();
    }

    /**
     * Null when the model can run this capability now. Otherwise a plain-language reason.
     * A model must be enabled, its provider active and configured, and its adapter must
     * declare the capability. Registry rows alone are not enough.
     */
    public function unusableReason(AiModel $model, Capability $capability): ?string
    {
        if ($model->provider->status !== ProviderStatus::Active) {
            return 'The provider is turned off.';
        }

        if (! $this->adapters->has($model->provider->key)) {
            return 'No adapter is registered for this provider.';
        }

        $adapter = $this->adapters->resolve($model->provider->key);

        if (! in_array($capability, $adapter->capabilities(), true)) {
            return "This provider cannot do {$capability->label()} generation.";
        }

        if (! $adapter->isConfigured()) {
            return 'Credentials are missing. Set the provider API key and restart the API.';
        }

        return null;
    }

    /**
     * Build the adapter for a routed model.
     */
    public function adapterFor(RoutedModel $routed): ProviderAdapter
    {
        if (! $this->adapters->has($routed->providerKey())) {
            throw ProviderNotConfigured::forProvider($routed->providerKey());
        }

        return $this->adapters->resolve($routed->providerKey());
    }
}
