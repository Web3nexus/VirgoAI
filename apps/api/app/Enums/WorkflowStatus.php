<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case AwaitingRevision = 'awaiting_revision';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Queued, self::Cancelled],
            self::Queued => [self::Running, self::Failed, self::Cancelled],
            self::Running => [self::AwaitingApproval, self::AwaitingRevision, self::Paused, self::Completed, self::Failed, self::Cancelled],
            self::AwaitingApproval => [self::Running, self::AwaitingRevision, self::Paused, self::Failed, self::Cancelled],
            self::AwaitingRevision => [self::Running, self::AwaitingApproval, self::Failed, self::Cancelled],
            self::Paused => [self::Running, self::Cancelled, self::Failed],
            self::Completed, self::Failed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running, self::AwaitingApproval, self::AwaitingRevision, self::Paused], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::AwaitingApproval => 'Awaiting approval',
            self::AwaitingRevision => 'Awaiting revision',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }
}
