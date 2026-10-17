<?php

namespace App\Ai\Data;

final class ToolModelOption
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $providerKey,
        public readonly bool $usable,
        public readonly ?string $reason,
        // What one unit costs the user, after margin. Null when the model has no price yet.
        public readonly ?string $priceLabel = null,
    ) {}
}
