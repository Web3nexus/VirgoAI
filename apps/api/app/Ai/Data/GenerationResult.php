<?php

namespace App\Ai\Data;

final class GenerationResult
{
    /**
     * @param  array<string, mixed>  $output  Normalized outputs (text, asset urls, etc.).
     * @param  array<string, mixed>  $usage  Token/unit usage metadata.
     * @param  array<string, mixed>  $raw  Raw provider payload (for debugging/audit).
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $output = [],
        public readonly array $usage = [],
        public readonly ?float $costUsd = null,
        public readonly ?string $providerKey = null,
        public readonly ?string $modelKey = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
        // Set when the provider accepted the work and it is still running. See AsyncProviderAdapter.
        public readonly ?array $job = null,
    ) {}

    /**
     * The provider accepted a long-running job. $job is what the poller needs to check it later.
     *
     * @param  array<string, mixed>  $job
     */
    public static function queued(array $job, string $providerKey, string $modelKey): self
    {
        return new self(ok: true, providerKey: $providerKey, modelKey: $modelKey, job: $job);
    }

    public function isQueued(): bool
    {
        return $this->job !== null;
    }

    /**
     * @param  array<string, mixed>  $job
     */
    public function withJob(array $job): self
    {
        return new self(
            ok: $this->ok,
            output: $this->output,
            usage: $this->usage,
            costUsd: $this->costUsd,
            providerKey: $this->providerKey,
            modelKey: $this->modelKey,
            error: $this->error,
            raw: $this->raw,
            job: $job,
        );
    }

    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $usage
     */
    public static function success(
        array $output,
        string $providerKey,
        string $modelKey,
        array $usage = [],
        ?float $costUsd = null,
        array $raw = [],
    ): self {
        return new self(
            ok: true,
            output: $output,
            usage: $usage,
            costUsd: $costUsd,
            providerKey: $providerKey,
            modelKey: $modelKey,
            raw: $raw,
        );
    }

    public static function failure(string $error, ?string $providerKey = null, ?string $modelKey = null): self
    {
        return new self(
            ok: false,
            providerKey: $providerKey,
            modelKey: $modelKey,
            error: $error,
        );
    }
}
