<?php

namespace App\Enums;

enum AutomationMode: string
{
    case Guided = 'guided';
    case Assisted = 'assisted';
    case FullHandoff = 'full_handoff';

    public function label(): string
    {
        return match ($this) {
            self::Guided => 'Guided',
            self::Assisted => 'Assisted',
            self::FullHandoff => 'Full handoff',
        };
    }
}
