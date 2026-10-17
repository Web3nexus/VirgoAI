<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_id', 'task_id', 'depends_on_task_id'])]
class TaskDependency extends Model
{
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'task_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class, 'depends_on_task_id');
    }
}
