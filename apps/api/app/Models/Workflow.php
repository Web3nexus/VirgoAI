<?php

namespace App\Models;

use App\Enums\AutomationMode;
use App\Enums\WorkflowStatus;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'template_key', 'mode', 'status', 'current_stage', 'config', 'started_at', 'finished_at', 'paused_at'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AutomationMode::class,
            'status' => WorkflowStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class)->orderBy('position');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function pendingApproval(): HasMany
    {
        return $this->approvalRequests()->where('status', 'pending');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
