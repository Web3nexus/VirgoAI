<?php

namespace App\Ai\Contracts;

use App\Ai\Data\GenerationRequest;
use App\Ai\Data\GenerationResult;
use App\Enums\Capability;

/**
 * A provider-independent adapter for one AI provider.
 *
 * Adapters normalize model selection, request construction, auth, submission, polling, result
 * retrieval, error handling, usage, and cost metadata — without forcing every provider into one
 * identical internal request shape. Each provider is integrated and verified individually.
 */
interface ProviderAdapter
{
    /** Stable key matching ai_providers.key. */
    public function key(): string;

    /**
     * Capabilities this adapter can serve (superset across its models).
     *
     * @return array<int, Capability>
     */
    public function capabilities(): array;

    /** Whether the runtime credentials required to call this provider are present. */
    public function isConfigured(): bool;

    public function generate(GenerationRequest $request): GenerationResult;
}
