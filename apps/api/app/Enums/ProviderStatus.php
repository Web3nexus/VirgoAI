<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Disabled = 'disabled';

    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Disabled => 'Disabled',
        };
    }
}
