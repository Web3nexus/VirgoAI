<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AdapterRegistry;
use App\Ai\Anthropic\AnthropicModelSync;
use App\Ai\Fal\FalModelSync;
use App\Billing\PriceSync;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * AI providers and the models they expose. Keys are reported as configured or not, never shown.
 */
class AdminProviderController extends Controller
{
    /** Human names and the key each provider reads, for providers not synced yet. */
    private const KNOWN = [
        'anthropic' => ['name' => 'Anthropic', 'credential' => 'ANTHROPIC_API_KEY'],
        'fal' => ['name' => 'fal.ai', 'credential' => 'FAL_KEY'],
        'echo' => ['name' => 'Echo (test model)', 'credential' => null],
    ];

    /**
     * Every provider the code can serve. A provider appears even before its first sync, marked "not synced",
     * so the list matches what is actually installed rather than whatever rows happen to be in the database.
     */
    public function index(AdapterRegistry $registry): JsonResponse
    {
        $rows = AiProvider::query()->with(['models.capabilities'])->get()->keyBy('key');

        $data = collect($registry->keys())
            ->sortBy(fn (string $key) => array_search($key, ['anthropic', 'fal', 'echo'], true))
            ->map(fn (string $key) => $rows->has($key)
                ? $this->present($rows->get($key), $registry)
                : $this->unsynced($key, $registry))
            ->values();

        return response()->json(['data' => $data]);
    }

    /** @return array<string, mixed> */
    private function unsynced(string $key, AdapterRegistry $registry): array
    {
        $configured = $registry->resolve($key)->isConfigured();

        return [
            'id' => null,
            'key' => $key,
            'name' => self::KNOWN[$key]['name'] ?? $key,
            'status' => 'not_synced',
            'priority' => null,
            'key_configured' => $configured,
            'adapter_registered' => true,
            'runtime_ready' => $configured,
            'credential_name' => self::KNOWN[$key]['credential'] ?? null,
            'models' => [],
        ];
    }

    public function update(Request $request, string $provider, AdapterRegistry $registry): JsonResponse
    {
        $record = $this->find($provider);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'string', Rule::in(array_column(ProviderStatus::cases(), 'value'))],
            'priority' => ['sometimes', 'integer', 'between:0,1000'],
        ]);

        $changes = [];
        foreach ($data as $field => $value) {
            $current = $field === 'status' ? $record->status->value : $record->{$field};
            if ($current !== $value) {
                $changes[$field] = ['from' => $current, 'to' => $value];
                $record->{$field} = $field === 'status' ? ProviderStatus::from($value) : $value;
            }
        }

        if ($changes !== []) {
            $record->save();
            AdminAuditLog::record($request->user(), 'provider.updated', $record, $changes);
        }

        return response()->json($this->present($record->refresh()->load('models.capabilities'), $registry));
    }

    public function updateModel(Request $request, string $provider, AiModel $model, AdapterRegistry $registry): JsonResponse
    {
        $record = $this->find($provider);
        abort_unless($model->ai_provider_id === $record->id, 404);

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'between:0,1000'],
        ]);

        $changes = [];
        foreach ($data as $field => $value) {
            if ($model->{$field} !== $value) {
                $changes[$field] = ['from' => $model->{$field}, 'to' => $value];
                $model->{$field} = $value;
            }
        }

        if ($changes !== []) {
            $model->save();
            AdminAuditLog::record($request->user(), 'provider.model_updated', $model, ['model' => $model->key] + $changes);
        }

        return response()->json($this->present($record->refresh()->load('models.capabilities'), $registry));
    }

    /**
     * Re-read the provider's models, and its prices where it has a price feed. Reports what it could not verify.
     */
    public function sync(Request $request, string $provider, AdapterRegistry $registry, AnthropicModelSync $anthropic, FalModelSync $fal, PriceSync $prices): JsonResponse
    {
        // A provider may not have a row yet. The model sync creates it, so only the key has to be known.
        abort_unless($registry->has($provider), 404);

        $errors = [];
        $summary = [];

        try {
            if ($provider === 'anthropic') {
                $summary['models'] = $anthropic->sync();
                $summary['prices'] = $prices->seedAnthropic();
            } elseif ($provider === 'fal') {
                $summary['models'] = $fal->sync();
                $summary['prices'] = $prices->syncFal();
                $errors = [...$summary['models']['errors'], ...$summary['prices']['errors']];
            } else {
                throw ValidationException::withMessages(['provider' => 'This provider has no sync. It is a test model.']);
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        AdminAuditLog::record($request->user(), 'provider.synced', AiProvider::query()->where('key', $provider)->first(), ['errors' => $errors]);

        $record = AiProvider::query()->where('key', $provider)->with('models.capabilities')->first();

        return response()->json([
            ...$summary,
            'errors' => $errors,
            'provider' => $record ? $this->present($record, $registry) : $this->unsynced($provider, $registry),
        ]);
    }

    private function find(string $key): AiProvider
    {
        return AiProvider::query()->where('key', $key)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(AiProvider $provider, AdapterRegistry $registry): array
    {
        return [
            'id' => $provider->id,
            'key' => $provider->key,
            'name' => $provider->name,
            'status' => $provider->status->value,
            'priority' => $provider->priority,
            'key_configured' => $provider->isConfigured(),
            'adapter_registered' => $registry->has($provider->key),
            'runtime_ready' => $provider->runtimeConfigured(),
            'credential_name' => $provider->credentials_key,
            'models' => $provider->models->sortBy('priority')->values()->map(fn (AiModel $model) => [
                'id' => $model->id,
                'key' => $model->key,
                'name' => $model->name,
                'enabled' => (bool) $model->enabled,
                'priority' => $model->priority,
                'capabilities' => $model->capabilities->pluck('capability')->values(),
            ])->values(),
        ];
    }
}
