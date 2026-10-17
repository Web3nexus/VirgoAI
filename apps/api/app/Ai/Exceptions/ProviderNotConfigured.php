<?php

namespace App\Ai\Exceptions;

use RuntimeException;

class ProviderNotConfigured extends RuntimeException
{
    public static function forProvider(string $key): self
    {
        return new self("Provider [{$key}] has no registered adapter or is missing credentials.");
    }
}
