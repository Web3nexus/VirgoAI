<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function view(User $user, ApprovalRequest $approval): bool
    {
        return $this->owns($user, $approval);
    }

    public function decide(User $user, ApprovalRequest $approval): bool
    {
        return $this->owns($user, $approval);
    }

    protected function owns(User $user, ApprovalRequest $approval): bool
    {
        return $approval->workflow()->whereHas(
            'project',
            fn ($query) => $query->where('user_id', $user->id),
        )->exists();
    }
}
