<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
    case Regenerated = 'regenerated';
    case Rejected = 'rejected';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isResolved(): bool
    {
        return ! $this->isPending();
    }
}
