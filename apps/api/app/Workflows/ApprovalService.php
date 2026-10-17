<?php

namespace App\Workflows;

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;

/**
 * Approval & revision engine: creates checkpoints, records decisions (with full history), and
 * routes the workflow back into the engine. Never mutates task state directly outside the state
 * machine.
 */
class ApprovalService
{
    public function __construct(protected WorkflowStateMachine $states) {}

    public function requestFor(Workflow $workflow, WorkflowTask $task): ApprovalRequest
    {
        return $workflow->approvalRequests()->create([
            'workflow_task_id' => $task->id,
            'status' => ApprovalStatus::Pending,
            'policy' => $task->approval_policy,
            'summary' => "{$task->name} is ready for review.",
            'payload' => $task->output,
            'requested_at' => now(),
        ]);
    }

    public function approve(ApprovalRequest $request, User $user, ?string $notes = null): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user, $notes) {
            $this->resolve($request, ApprovalStatus::Approved, $user, 'approve', $notes);

            $task = $request->task;
            $this->states->transitionTask($task, TaskStatus::Completed);

            $workflow = $request->workflow;
            $workflow->current_stage = $task->stage;
            $workflow->save();
        });

        $this->engine()->advance($request->workflow->refresh());

        return $request->fresh();
    }

    /**
     * "Edit before continuing": persist an edited revision as the task output, then continue.
     *
     * @param  array<string, mixed>  $revision
     */
    public function revise(ApprovalRequest $request, User $user, array $revision, ?string $notes = null): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user, $revision, $notes) {
            $this->resolve($request, ApprovalStatus::RevisionRequested, $user, 'revise', $notes, $revision);

            $task = $request->task;
            $task->output = array_merge($task->output ?? [], $revision);
            $task->save();

            $this->states->transitionTask($task, TaskStatus::Completed);
        });

        $this->engine()->advance($request->workflow->refresh());

        return $request->fresh();
    }

    /**
     * Re-run the stage with no edits. The task re-executes and, if the policy still requires
     * approval, a fresh checkpoint is raised.
     */
    public function regenerate(ApprovalRequest $request, User $user, ?string $notes = null): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user, $notes) {
            $this->resolve($request, ApprovalStatus::Regenerated, $user, 'regenerate', $notes);
        });

        $workflow = $request->workflow->refresh();

        if ($workflow->status->canTransitionTo(WorkflowStatus::Running)) {
            $this->states->transition($workflow, WorkflowStatus::Running);
        }

        $this->engine()->executeTask($request->task->refresh());

        return $request->fresh();
    }

    public function reject(ApprovalRequest $request, User $user, ?string $notes = null): ApprovalRequest
    {
        DB::transaction(function () use ($request, $user, $notes) {
            $this->resolve($request, ApprovalStatus::Rejected, $user, 'reject', $notes);
        });

        $this->engine()->cancel($request->workflow->refresh());

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $revision
     */
    protected function resolve(
        ApprovalRequest $request,
        ApprovalStatus $status,
        User $user,
        string $decision,
        ?string $notes,
        ?array $revision = null,
    ): void {
        $request->status = $status;
        $request->resolved_at = now();
        $request->resolved_by = $user->id;
        $request->save();

        $request->decisions()->create([
            'user_id' => $user->id,
            'decision' => $decision,
            'notes' => $notes,
            'revision' => $revision,
        ]);
    }

    protected function engine(): WorkflowEngine
    {
        return app(WorkflowEngine::class);
    }
}
