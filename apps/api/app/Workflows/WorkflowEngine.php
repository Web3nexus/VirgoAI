<?php

namespace App\Workflows;

use App\Enums\AutomationMode;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Jobs\RunWorkflowTask;
use App\Models\Project;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Durable orchestration for a project's content pipeline.
 *
 * Every stage is its own persisted task/job; the engine only ever decides *which* tasks are
 * eligible and routes state changes through {@see WorkflowStateMachine}. It is resumable: all
 * state lives in PostgreSQL, so a worker restart or browser refresh never loses progress.
 */
class WorkflowEngine
{
    public function __construct(
        protected WorkflowStateMachine $states,
        protected HandlerRegistry $handlers,
        protected ApprovalService $approvals,
    ) {}

    public function start(
        Project $project,
        string $templateKey = WorkflowTemplates::DEFAULT,
        ?AutomationMode $mode = null,
    ): Workflow {
        $template = WorkflowTemplates::get($templateKey);
        $mode ??= AutomationMode::Guided;

        $workflow = DB::transaction(function () use ($project, $template, $mode) {
            $workflow = $project->workflows()->create([
                'template_key' => $template->key,
                'mode' => $mode,
                'status' => WorkflowStatus::Draft,
                'config' => ['template_name' => $template->name],
            ]);

            $tasks = [];

            foreach ($template->stages as $position => $stage) {
                $tasks[$stage->key] = $workflow->tasks()->create([
                    'key' => $stage->key,
                    'stage' => $stage->key,
                    'name' => $stage->name,
                    'status' => TaskStatus::Pending,
                    'position' => $position,
                    'approval_policy' => $stage->approvalPolicy,
                    'handler' => $stage->handler,
                    'input' => $stage->toTaskInput(),
                ]);
            }

            foreach ($template->stages as $stage) {
                foreach ($stage->dependsOn as $depKey) {
                    $workflow->dependencies()->create([
                        'task_id' => $tasks[$stage->key]->id,
                        'depends_on_task_id' => $tasks[$depKey]->id,
                    ]);
                }
            }

            $this->states->transition($workflow, WorkflowStatus::Queued);

            return $workflow;
        });

        $this->advance($workflow);

        return $workflow->fresh();
    }

    /**
     * Start every currently-eligible task and settle the workflow if it is done.
     */
    public function advance(Workflow $workflow): Workflow
    {
        $workflow->refresh();

        if ($workflow->status->isTerminal() || $workflow->status === WorkflowStatus::Paused) {
            return $workflow;
        }

        if ($workflow->approvalRequests()->where('status', 'pending')->exists()) {
            if ($workflow->status->canTransitionTo(WorkflowStatus::AwaitingApproval)) {
                $this->states->transition($workflow, WorkflowStatus::AwaitingApproval);
            }

            return $workflow;
        }

        if ($workflow->status !== WorkflowStatus::Running
            && $workflow->status->canTransitionTo(WorkflowStatus::Running)) {
            $this->states->transition($workflow, WorkflowStatus::Running);
        }

        $eligible = $workflow->tasks()
            ->where('status', TaskStatus::Pending->value)
            ->orderBy('position')
            ->get()
            ->filter(fn (WorkflowTask $task) => $this->dependenciesSettled($task));

        foreach ($eligible as $task) {
            // Re-read each task: with a synchronous queue an earlier dispatch may already have
            // advanced the graph, making later entries stale.
            $fresh = $task->fresh();

            if ($fresh === null || $fresh->status !== TaskStatus::Pending || ! $this->dependenciesSettled($fresh)) {
                continue;
            }

            $this->states->transitionTask($fresh, TaskStatus::Queued);
            RunWorkflowTask::dispatch($fresh->id);
        }

        return $this->settleIfFinished($workflow->fresh());
    }

    public function executeTask(WorkflowTask $task): void
    {
        $workflow = $task->workflow()->first();

        if ($workflow === null || $workflow->status === WorkflowStatus::Cancelled) {
            return;
        }

        $this->states->transitionTask($task, TaskStatus::Running);
        $task->increment('attempts');
        $task->refresh();

        try {
            $output = $this->handlers->resolve($task->handler)->handle($task);
        } catch (Throwable $e) {
            $task->error = $e->getMessage();
            $task->save();
            $this->states->transitionTask($task, TaskStatus::Failed);
            $this->failWorkflow($workflow);

            return;
        }

        $task->output = $output;
        $task->error = null;
        $task->finished_at = now();

        $requiresApproval = $task->approval_policy->requiresApproval(
            $workflow->mode,
            (bool) ($task->input['user_configured'] ?? false),
        );

        if ($requiresApproval) {
            $this->states->transitionTask($task, TaskStatus::AwaitingApproval);
            $this->approvals->requestFor($workflow, $task);
            $this->advance($workflow->refresh());

            return;
        }

        $this->states->transitionTask($task, TaskStatus::Completed);
        $workflow->current_stage = $task->stage;
        $workflow->save();

        $this->advance($workflow->refresh());
    }

    public function pause(Workflow $workflow): Workflow
    {
        if ($workflow->status->canTransitionTo(WorkflowStatus::Paused)) {
            $this->states->transition($workflow, WorkflowStatus::Paused);
        }

        return $workflow->fresh();
    }

    public function resume(Workflow $workflow): Workflow
    {
        if ($workflow->status->canTransitionTo(WorkflowStatus::Running)) {
            $this->states->transition($workflow, WorkflowStatus::Running);
        }

        return $this->advance($workflow->fresh());
    }

    public function cancel(Workflow $workflow): Workflow
    {
        DB::transaction(function () use ($workflow) {
            $workflow->tasks()
                ->whereIn('status', [
                    TaskStatus::Pending->value,
                    TaskStatus::Queued->value,
                    TaskStatus::Running->value,
                    TaskStatus::AwaitingApproval->value,
                ])
                ->get()
                ->each(fn (WorkflowTask $task) => $this->states->transitionTask($task, TaskStatus::Cancelled));

            $workflow->approvalRequests()
                ->where('status', 'pending')
                ->update(['status' => 'rejected', 'resolved_at' => now()]);

            if ($workflow->status->canTransitionTo(WorkflowStatus::Cancelled)) {
                $this->states->transition($workflow, WorkflowStatus::Cancelled);
            }
        });

        return $workflow->fresh();
    }

    protected function dependenciesSettled(WorkflowTask $task): bool
    {
        $dependencyIds = $task->dependencies()->pluck('depends_on_task_id');

        if ($dependencyIds->isEmpty()) {
            return true;
        }

        return ! WorkflowTask::whereIn('id', $dependencyIds)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Skipped->value])
            ->exists();
    }

    protected function settleIfFinished(Workflow $workflow): Workflow
    {
        if ($workflow->approvalRequests()->where('status', 'pending')->exists()) {
            return $workflow;
        }

        $outstanding = $workflow->tasks()
            ->whereNotIn('status', [
                TaskStatus::Completed->value,
                TaskStatus::Skipped->value,
                TaskStatus::Cancelled->value,
            ])
            ->exists();

        if (! $outstanding && $workflow->status === WorkflowStatus::Running) {
            $this->states->transition($workflow, WorkflowStatus::Completed);
        }

        return $workflow->fresh();
    }

    protected function failWorkflow(Workflow $workflow): void
    {
        $workflow->refresh();

        if ($workflow->status->canTransitionTo(WorkflowStatus::Failed)) {
            $this->states->transition($workflow, WorkflowStatus::Failed);
        }
    }
}
