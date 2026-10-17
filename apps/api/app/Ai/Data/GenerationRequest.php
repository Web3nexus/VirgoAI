<?php

namespace App\Ai\Data;

use App\Enums\Capability;

final class GenerationRequest
{
    /**
     * @param  array<string, mixed>  $inputs  Media/reference inputs (urls, base64, ids).
     * @param  array<string, mixed>  $options  Provider-agnostic generation options.
     */
    public function __construct(
        public readonly Capability $capability,
        public readonly string $modelKey,
        public readonly string $prompt = '',
        public readonly array $inputs = [],
        public readonly array $options = [],
        public readonly ?string $projectId = null,
        public readonly ?string $workflowTaskId = null,
        public readonly ?string $idempotencyKey = null,
        // The model the user picked. Empty means "let the router choose".
        public readonly ?string $requestedModelKey = null,
    ) {}

    public function withModelKey(string $modelKey): self
    {
        return new self(
            capability: $this->capability,
            modelKey: $modelKey,
            prompt: $this->prompt,
            inputs: $this->inputs,
            options: $this->options,
            projectId: $this->projectId,
            workflowTaskId: $this->workflowTaskId,
            idempotencyKey: $this->idempotencyKey,
            requestedModelKey: $this->requestedModelKey,
        );
    }
}
