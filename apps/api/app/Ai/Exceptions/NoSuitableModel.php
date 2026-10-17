<?php

namespace App\Ai\Exceptions;

use App\Enums\Capability;
use RuntimeException;

class NoSuitableModel extends RuntimeException
{
    public static function forCapability(Capability $capability): self
    {
        return new self(
            "No configured provider can serve the [{$capability->value}] capability. "
            .'Enable a provider and set its credentials, then register its models.'
        );
    }

    public static function forModel(string $modelKey, Capability $capability, string $reason): self
    {
        return new self("Model [{$modelKey}] cannot run [{$capability->value}] right now: {$reason}");
    }
}
