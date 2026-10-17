<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\WorkflowTask;
use App\Workflows\WorkflowEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunWorkflowTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $taskId) {}

    public function handle(WorkflowEngine $engine): void
    {
        $task = WorkflowTask::with('workflow')->find($this->taskId);

        // Idempotency guard: ignore duplicate/at-least-once deliveries for tasks not queued.
        if ($task === null || $task->status !== TaskStatus::Queued) {
            return;
        }

        $engine->executeTask($task);
    }
}
