<?php

namespace App\Ai\Anthropic;

use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Copies the Claude models this API key can use into the registry, read from Anthropic's Models API.
 *
 * Nothing is guessed. The list comes from GET /v1/models with the owner's key, so a model shows up
 * only if the key can call it. Only models whose lifecycle is `active` are kept.
 */
class AnthropicModelSync
{
    public const MODELS_URL = 'https://api.anthropic.com/v1/models';

    private const API_VERSION = '2023-06-01';

    /**
     * @return array{provider: string, upserted: int, skipped: int}
     */
    public function sync(int $maxPages = 20): array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured; cannot sync the Claude model list.');
        }

        $provider = AiProvider::updateOrCreate(
            ['key' => 'anthropic'],
            [
                'name' => 'Anthropic',
                'status' => ProviderStatus::Active,
                'credentials_key' => 'ANTHROPIC_API_KEY',
                'priority' => 50,
            ],
        );

        $upserted = 0;
        $skipped = 0;
        $afterId = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = ['limit' => 100];
            if ($afterId !== null) {
                $query['after_id'] = $afterId;
            }

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ])->acceptJson()->timeout(30)->get(self::MODELS_URL, $query);

            if ($response->failed()) {
                throw new RuntimeException(
                    'Anthropic model list failed: HTTP '.$response->status().' '.($response->json('error.message') ?? ''),
                );
            }

            foreach ($response->json('data') ?? [] as $item) {
                $id = (string) ($item['id'] ?? '');
                $lifecycle = (string) ($item['lifecycle'] ?? 'active');

                if ($id === '' || $lifecycle !== 'active') {
                    $skipped++;

                    continue;
                }

                $model = AiModel::updateOrCreate(
                    ['ai_provider_id' => $provider->id, 'key' => $id],
                    [
                        'name' => (string) ($item['display_name'] ?? $id),
                        'enabled' => true,
                        'priority' => 50,
                        'meta' => [
                            'lifecycle' => $lifecycle,
                            'max_input_tokens' => $item['max_input_tokens'] ?? null,
                            'max_tokens' => $item['max_tokens'] ?? null,
                        ],
                    ],
                );

                ProviderCapability::updateOrCreate(
                    ['ai_model_id' => $model->id, 'capability' => Capability::Text->value],
                    [],
                );

                $upserted++;
            }

            if (! $response->json('has_more')) {
                break;
            }

            $afterId = $response->json('last_id');
            if (! is_string($afterId) || $afterId === '') {
                break;
            }
        }

        return ['provider' => $provider->key, 'upserted' => $upserted, 'skipped' => $skipped];
    }

    private function apiKey(): ?string
    {
        $key = ProviderCredentials::get('ANTHROPIC_API_KEY');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
