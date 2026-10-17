<?php

namespace App\Models;

use App\Enums\ApprovalPolicy;
use App\Enums\TaskStatus;
use Database\Factories\WorkflowTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['workflow_id', 'key', 'stage', 'name', 'status', 'position', 'approval_policy', 'handler', 'input', 'output', 'error', 'attempts', 'max_attempts', 'started_at', 'finished_at'])]
class WorkflowTask extends Model
{
    /** @use HasFactory<WorkflowTaskFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'approval_policy' => ApprovalPolicy::class,
            'input' => 'array',
            'output' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function dependsOn(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkflowTask::class,
            TaskDependency::class,
            'task_id',
            'id',
            'id',
            'depends_on_task_id',
        );
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_task_id');
    }

    public function isSettled(): bool
    {
        return $this->status->isSettled();
    }
}
