<?php

namespace App\Billing;

/**
 * Converts between dollars and the integer micro-dollars the wallet stores.
 */
final class Money
{
    public static function fromUsd(float|string|int $usd): int
    {
        return (int) round((float) $usd * (int) config('pricing.micros_per_usd', 1_000_000));
    }

    public static function toUsd(int $micros): float
    {
        return round($micros / (int) config('pricing.micros_per_usd', 1_000_000), 6);
    }

    /** Micro-dollars per platform token. 1 token = $0.001, so 1,000 tokens = $1. */
    public const MICROS_PER_TOKEN = 1000;

    /** The customer-facing unit: tokens. Fractions are kept so a small action still shows a real cost. */
    public static function toTokens(int $micros): float
    {
        return round($micros / self::MICROS_PER_TOKEN, 1);
    }

    /** Dollars with a fixed number of decimals, for display text. */
    public static function format(int $micros, int $decimals = 2): string
    {
        $sign = $micros < 0 ? '-' : '';

        return $sign.'$'.number_format(abs(self::toUsd($micros)), $decimals);
    }

    /**
     * Cost of `$quantity` units at `$unitMicros` each, rounded up so a partial micro is never free.
     */
    public static function times(int $unitMicros, int|float $quantity): int
    {
        return (int) ceil($unitMicros * $quantity);
    }

    /**
     * Cost of `$tokens` at a per-million-token price, rounded up.
     */
    public static function tokens(int $tokens, int $perMillionMicros): int
    {
        return (int) ceil($tokens * $perMillionMicros / 1_000_000);
    }
}
