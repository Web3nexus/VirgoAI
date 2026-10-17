<?php

namespace App\Workflows;

use App\Models\WorkflowTask;

interface StageHandler
{
    /**
     * Produce the stage output for a task.
     *
     * @return array<string, mixed>
     */
    public function handle(WorkflowTask $task): array;
}
