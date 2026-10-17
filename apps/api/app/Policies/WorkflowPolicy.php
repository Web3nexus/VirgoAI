<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    public function view(User $user, Workflow $workflow): bool
    {
        return $workflow->project()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $workflow->project()->where('user_id', $user->id)->exists();
    }
}
