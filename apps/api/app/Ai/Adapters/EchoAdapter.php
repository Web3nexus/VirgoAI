<?php

namespace App\Ai\Adapters;

use App\Ai\Contracts\ProviderAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Enums\Capability;

/**
 * Development/testing adapter. Deterministic, offline, and zero-cost.
 *
 * It exists so the provider layer, model router, and workflow stages can be exercised end-to-end
 * without credentials. It is registered in config/virgoai.php and seeded only in local/testing
 * environments; it must never be used for real production content.
 */
class EchoAdapter implements ProviderAdapter
{
    public function key(): string
    {
        return 'echo';
    }

    public function capabilities(): array
    {
        // Text only. Echo repeats the prompt, so it must never be picked for image, video, or audio.
        return [Capability::Text];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        return GenerationResult::success(
            output: [
                'capability' => $request->capability->value,
                'model' => $request->modelKey,
                'echo' => $request->prompt,
                'inputs' => count($request->inputs),
            ],
            providerKey: $this->key(),
            modelKey: $request->modelKey,
            usage: ['units' => str_word_count($request->prompt)],
            costUsd: 0.0,
        );
    }
}
