<?php

namespace App\Ai\Fal;

use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Checks each fal endpoint this app calls against fal's live catalog and registers the ones that pass.
 *
 * It asks fal for exactly the endpoints in FalEndpoints (find mode, with their OpenAPI schema). An
 * endpoint is registered only if fal still lists it as active, its category matches, and every input
 * field the app sends still exists in its schema. A failing endpoint is disabled and reported, so a
 * model can never appear in the picker when it would fail on a paid request.
 */
class FalModelSync
{
    public const BASE_URL = 'https://api.fal.ai/v1/models';

    /**
     * @return array{provider: string, upserted: int, errors: list<string>}
     */
    public function sync(): array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('FAL_KEY is not configured; cannot verify the fal endpoints.');
        }

        $provider = AiProvider::updateOrCreate(
            ['key' => 'fal'],
            [
                'name' => 'fal.ai',
                'status' => ProviderStatus::Active,
                'credentials_key' => 'FAL_KEY',
                'priority' => 100,
            ],
        );

        $models = $this->fetchEndpoints($apiKey);
        $upserted = 0;
        $errors = [];

        foreach (FalEndpoints::all() as $endpointId => $spec) {
            $problems = $this->verify($endpointId, $spec, $models[$endpointId] ?? null);

            if ($problems !== []) {
                $errors = [...$errors, ...$problems];

                if ($spec['selectable']) {
                    AiModel::query()
                        ->where('ai_provider_id', $provider->id)
                        ->where('key', $endpointId)
                        ->update(['enabled' => false]);
                }

                continue;
            }

            if ($spec['selectable']) {
                $this->upsertModel($provider, $endpointId, $spec, $models[$endpointId]);
                $upserted++;
            }
        }

        return ['provider' => $provider->key, 'upserted' => $upserted, 'errors' => $errors];
    }

    /**
     * @return array<string, array<string, mixed>> keyed by endpoint id
     */
    private function fetchEndpoints(string $apiKey): array
    {
        // The query is built by hand: fal takes repeated endpoint_id parameters, not endpoint_id[0].
        $query = collect(array_keys(FalEndpoints::all()))
            ->map(fn (string $id) => 'endpoint_id='.rawurlencode($id))
            ->push('expand=openapi-3.0')
            ->implode('&');

        $response = Http::withHeaders(['Authorization' => 'Key '.$apiKey])
            ->acceptJson()
            ->timeout(30)
            ->get(self::BASE_URL.'?'.$query);

        if ($response->failed()) {
            throw new RuntimeException('fal model lookup failed: HTTP '.$response->status());
        }

        $byId = [];
        foreach ($response->json('models') ?? [] as $item) {
            $id = (string) ($item['endpoint_id'] ?? '');
            if ($id !== '') {
                $byId[$id] = $item;
            }
        }

        return $byId;
    }

    /**
     * @param  array{capability: Capability, category: string, selectable: bool, fields: list<string>}  $spec
     * @param  array<string, mixed>|null  $item
     * @return list<string> Empty when the endpoint is safe to call.
     */
    private function verify(string $endpointId, array $spec, ?array $item): array
    {
        if ($item === null) {
            return ["fal did not return [{$endpointId}]. It may have been removed."];
        }

        $meta = $item['metadata'] ?? [];

        if (($meta['status'] ?? null) !== 'active') {
            return ["fal reports [{$endpointId}] as not active."];
        }

        if (($meta['category'] ?? null) !== $spec['category']) {
            return ["fal lists [{$endpointId}] as [".($meta['category'] ?? 'none')."], expected [{$spec['category']}]."];
        }

        $properties = self::inputProperties($item['openapi'] ?? null);

        if ($properties === null) {
            return ["fal sent no input schema for [{$endpointId}], so its fields cannot be checked."];
        }

        $missing = array_values(array_diff($spec['fields'], $properties));

        if ($missing !== []) {
            return ["fal's schema for [{$endpointId}] no longer has: ".implode(', ', $missing).'.'];
        }

        return [];
    }

    /**
     * The input field names of the endpoint's POST route, read from its OpenAPI document.
     *
     * @return list<string>|null
     */
    public static function inputProperties(?array $openapi): ?array
    {
        if ($openapi === null) {
            return null;
        }

        foreach ($openapi['paths'] ?? [] as $operations) {
            $schema = $operations['post']['requestBody']['content']['application/json']['schema'] ?? null;

            if ($schema === null) {
                continue;
            }

            $schema = self::resolveRef($openapi, $schema);

            if (is_array($schema) && isset($schema['properties']) && is_array($schema['properties'])) {
                return array_keys($schema['properties']);
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private static function resolveRef(array $openapi, array $schema): ?array
    {
        $ref = $schema['$ref'] ?? null;

        if (! is_string($ref)) {
            return $schema;
        }

        $name = basename(str_replace('\\', '/', $ref));

        return $openapi['components']['schemas'][$name] ?? null;
    }

    /**
     * @param  array{capability: Capability, category: string, selectable: bool, fields: list<string>}  $spec
     * @param  array<string, mixed>  $item
     */
    private function upsertModel(AiProvider $provider, string $endpointId, array $spec, array $item): void
    {
        $meta = $item['metadata'] ?? [];

        $model = AiModel::updateOrCreate(
            ['ai_provider_id' => $provider->id, 'key' => $endpointId],
            [
                'name' => (string) ($meta['display_name'] ?? $endpointId),
                'enabled' => true,
                'priority' => 100,
                'meta' => [
                    'fal_category' => $meta['category'] ?? null,
                    'fal_status' => $meta['status'] ?? null,
                    'fal_description' => $meta['description'] ?? null,
                    'fal_model_url' => $meta['model_url'] ?? null,
                    'verified_fields' => $spec['fields'],
                ],
            ],
        );

        ProviderCapability::updateOrCreate(
            ['ai_model_id' => $model->id, 'capability' => $spec['capability']->value],
            [],
        );
    }

    private function apiKey(): ?string
    {
        $key = ProviderCredentials::get('FAL_KEY');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
