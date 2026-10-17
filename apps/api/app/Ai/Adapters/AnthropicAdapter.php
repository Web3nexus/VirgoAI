<?php

namespace App\Ai\Adapters;

use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Enums\Capability;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anthropic Messages API adapter for text (story, director script, prompts, review).
 *
 * The request is synchronous: Claude replies in one response. The system prompt comes from
 * $request->options['system'] and is never stored on the generation record. The key is sent only
 * in the x-api-key header and never appears in a returned error or a log line.
 */
class AnthropicAdapter implements ProviderAdapter
{
    private const MESSAGES_URL = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function key(): string
    {
        return 'anthropic';
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return [Capability::Text];
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        $apiKey = $this->apiKey();

        if ($apiKey === null) {
            return GenerationResult::failure('Anthropic API key is not configured.', $this->key(), $request->modelKey);
        }

        if ($request->capability !== Capability::Text) {
            return GenerationResult::failure(
                "Anthropic adapter does not handle [{$request->capability->value}] generation.",
                $this->key(),
                $request->modelKey,
            );
        }

        $body = [
            'model' => $request->modelKey,
            'max_tokens' => (int) config('virgoai.anthropic.max_tokens', 8192),
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
        ];

        $system = $request->options['system'] ?? null;
        if (is_string($system) && $system !== '') {
            $body['system'] = $system;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('virgoai.anthropic.timeout_seconds', 180))
                ->post(self::MESSAGES_URL, $body);
        } catch (Throwable $e) {
            Log::warning('anthropic request failed', ['model' => $request->modelKey, 'error' => $e->getMessage()]);

            return GenerationResult::failure('Could not reach Anthropic. Check the network and try again.', $this->key(), $request->modelKey);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? 'Request failed.';

            return GenerationResult::failure(
                "Anthropic returned HTTP {$response->status()}: {$message}",
                $this->key(),
                $request->modelKey,
            );
        }

        if ($response->json('stop_reason') === 'max_tokens') {
            return GenerationResult::failure(
                'The reply hit the token limit before it finished. Shorten the request or raise ANTHROPIC_MAX_TOKENS.',
                $this->key(),
                $request->modelKey,
            );
        }

        $text = collect($response->json('content') ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if ($text === '') {
            return GenerationResult::failure('Anthropic returned no text.', $this->key(), $request->modelKey);
        }

        return GenerationResult::success(
            output: ['text' => $text],
            providerKey: $this->key(),
            modelKey: $request->modelKey,
            usage: [
                'input_tokens' => $response->json('usage.input_tokens'),
                'output_tokens' => $response->json('usage.output_tokens'),
            ],
            costUsd: null,
        );
    }

    /** The key comes from config or the environment. It is never returned or logged. */
    private function apiKey(): ?string
    {
        $key = ProviderCredentials::get('ANTHROPIC_API_KEY');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
