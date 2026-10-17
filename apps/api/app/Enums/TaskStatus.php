<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case AwaitingApproval = 'awaiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Queued, self::Skipped, self::Cancelled],
            self::Queued => [self::Running, self::Pending, self::Failed, self::Cancelled],
            self::Running => [self::AwaitingApproval, self::Completed, self::Failed, self::Cancelled],
            self::AwaitingApproval => [self::Completed, self::Running, self::Cancelled, self::Failed],
            self::Completed => [self::Running],
            self::Failed => [self::Queued, self::Cancelled],
            self::Skipped, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Completed, self::Skipped, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::AwaitingApproval => 'Awaiting approval',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
            self::Cancelled => 'Cancelled',
        };
    }
}
