<?php

namespace App\Billing;

/**
 * A price for one run: what the provider costs us, and what the user pays.
 */
final class Quote
{
    public function __construct(
        public readonly int $costMicros,
        public readonly int $priceMicros,
        public readonly string $unit,
        public readonly float $quantity,
        public readonly bool $estimate,
    ) {}

    public static function free(): self
    {
        return new self(0, 0, 'free', 0, false);
    }
}
