<?php

namespace App\Models;

use App\Enums\ApprovalPolicy;
use App\Enums\ApprovalStatus;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workflow_id', 'workflow_task_id', 'status', 'policy', 'summary', 'payload', 'requested_at', 'resolved_at', 'resolved_by'])]
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'policy' => ApprovalPolicy::class,
            'payload' => 'array',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'workflow_task_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }
}
