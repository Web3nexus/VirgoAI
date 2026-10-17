<?php

namespace App\Enums;

enum ApprovalPolicy: string
{
    case Always = 'always';
    case GuidedOnly = 'guided_only';
    case UserConfigured = 'user_configured';
    case CriticalOnly = 'critical_only';
    case NotRequired = 'not_required';

    public function requiresApproval(AutomationMode $mode, bool $userConfigured = false): bool
    {
        return match ($this) {
            self::Always => true,
            self::NotRequired => false,
            self::GuidedOnly => $mode === AutomationMode::Guided,
            // Guided and assisted runs stop here. Full handoff runs straight through.
            self::CriticalOnly => $mode !== AutomationMode::FullHandoff,
            self::UserConfigured => $userConfigured,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Always => 'Always required',
            self::GuidedOnly => 'Guided only',
            self::CriticalOnly => 'Guided and assisted',
            self::UserConfigured => 'User-configured',
            self::NotRequired => 'Not required',
        };
    }
}
