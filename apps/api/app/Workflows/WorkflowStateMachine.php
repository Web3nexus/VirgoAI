<?php

namespace App\Workflows;

use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Exceptions\InvalidWorkflowTransition;
use App\Models\Workflow;
use App\Models\WorkflowTask;

/**
 * The single source of truth for legal workflow/task state changes.
 * Controllers and jobs must route status changes through this service.
 */
class WorkflowStateMachine
{
    public function transition(Workflow $workflow, WorkflowStatus $to): Workflow
    {
        $from = $workflow->status;

        if ($from === $to) {
            return $workflow;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidWorkflowTransition::between('workflow', $from->value, $to->value);
        }

        $workflow->status = $to;

        match ($to) {
            WorkflowStatus::Running => $workflow->started_at ??= now(),
            WorkflowStatus::Paused => $workflow->paused_at = now(),
            WorkflowStatus::Completed, WorkflowStatus::Failed, WorkflowStatus::Cancelled => $workflow->finished_at = now(),
            default => null,
        };

        if ($to === WorkflowStatus::Running) {
            $workflow->paused_at = null;
        }

        $workflow->save();

        return $workflow;
    }

    public function transitionTask(WorkflowTask $task, TaskStatus $to): WorkflowTask
    {
        $from = $task->status;

        if ($from === $to) {
            return $task;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidWorkflowTransition::between('task', $from->value, $to->value);
        }

        $task->status = $to;

        match ($to) {
            TaskStatus::Running => $task->started_at ??= now(),
            TaskStatus::Completed, TaskStatus::Failed, TaskStatus::Skipped, TaskStatus::Cancelled => $task->finished_at = now(),
            default => null,
        };

        $task->save();

        return $task;
    }
}
