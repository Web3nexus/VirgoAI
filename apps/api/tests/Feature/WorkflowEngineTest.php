<?php

use App\Enums\ApprovalStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Exceptions\InvalidWorkflowTransition;
use App\Jobs\RunWorkflowTask;
use App\Models\Project;
use App\Models\User;
use App\Workflows\ApprovalService;
use App\Workflows\WorkflowEngine;
use App\Workflows\WorkflowStateMachine;

function projectFor(User $user): Project
{
    return Project::factory()->create(['user_id' => $user->id]);
}

it('starts a workflow and runs it up to the first approval checkpoint', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);

    $workflow->load('tasks');

    expect($workflow->status)->toBe(WorkflowStatus::AwaitingApproval)
        ->and($workflow->tasks)->toHaveCount(4)
        ->and($workflow->tasks->firstWhere('key', 'story')->status)->toBe(TaskStatus::AwaitingApproval)
        ->and($workflow->tasks->firstWhere('key', 'script')->status)->toBe(TaskStatus::Pending)
        ->and($workflow->approvalRequests()->where('status', 'pending')->count())->toBe(1);
});

it('creates dependency edges between tasks', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $workflow->load('tasks');
    $script = $workflow->tasks->firstWhere('key', 'script');

    expect($script->dependencies()->count())->toBe(1)
        ->and($script->dependsOn()->pluck('key')->all())->toBe(['story']);
});

it('completes the whole workflow after approving the checkpoint', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $engine = app(WorkflowEngine::class);
    $workflow = $engine->start($project);
    $approval = $workflow->approvalRequests()->first();

    app(ApprovalService::class)->approve($approval, $user);

    $workflow->refresh()->load('tasks');

    expect($workflow->status)->toBe(WorkflowStatus::Completed)
        ->and($workflow->tasks->where('status', TaskStatus::Completed))->toHaveCount(4)
        ->and($workflow->tasks->where('status', TaskStatus::AwaitingApproval))->toHaveCount(0);
});

it('records an approval decision with the acting user', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $approval = $workflow->approvalRequests()->first();

    app(ApprovalService::class)->approve($approval, $user, 'Looks great');

    $approval->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->resolved_by)->toBe($user->id)
        ->and($approval->decisions()->first()->decision)->toBe('approve')
        ->and($approval->decisions()->first()->notes)->toBe('Looks great');
});

it('applies a revision and continues', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $approval = $workflow->approvalRequests()->first();
    $story = $workflow->tasks()->where('key', 'story')->first();

    app(ApprovalService::class)->revise($approval, $user, ['summary' => 'Edited by user'], 'tweak');

    $story->refresh();

    expect($story->output['summary'])->toBe('Edited by user')
        ->and($story->status)->toBe(TaskStatus::Completed)
        ->and($workflow->refresh()->status)->toBe(WorkflowStatus::Completed);
});

it('regenerates a stage and raises a fresh checkpoint', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $approval = $workflow->approvalRequests()->first();
    $story = $workflow->tasks()->where('key', 'story')->first();

    app(ApprovalService::class)->regenerate($approval, $user);

    $approval->refresh();
    $story->refresh();
    $workflow->refresh();

    expect($approval->status)->toBe(ApprovalStatus::Regenerated)
        ->and($story->attempts)->toBe(2)
        ->and($workflow->status)->toBe(WorkflowStatus::AwaitingApproval)
        ->and($workflow->approvalRequests()->where('status', 'pending')->count())->toBe(1);
});

it('rejects an approval and cancels the workflow', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $approval = $workflow->approvalRequests()->first();

    app(ApprovalService::class)->reject($approval, $user, 'Not acceptable');

    $workflow->refresh()->load('tasks');

    expect($approval->refresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and($workflow->status)->toBe(WorkflowStatus::Cancelled)
        ->and($workflow->tasks->where('status', TaskStatus::Cancelled)->count())->toBeGreaterThan(0);
});

it('pauses a workflow awaiting approval and resumes it back to the checkpoint', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $engine = app(WorkflowEngine::class);
    $workflow = $engine->start($project);

    $paused = $engine->pause($workflow);
    expect($paused->status)->toBe(WorkflowStatus::Paused)
        ->and($paused->paused_at)->not->toBeNull();

    $resumed = $engine->resume($paused);
    expect($resumed->status)->toBe(WorkflowStatus::AwaitingApproval);
});

it('forbids illegal workflow transitions', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $states = app(WorkflowStateMachine::class);

    // awaiting_approval cannot jump straight to completed
    $states->transition($workflow, WorkflowStatus::Completed);
})->throws(InvalidWorkflowTransition::class);

it('never starts downstream tasks while a checkpoint is pending', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $workflow->load('tasks');

    expect($workflow->tasks->firstWhere('key', 'script')->started_at)->toBeNull()
        ->and($workflow->tasks->firstWhere('key', 'scenes')->started_at)->toBeNull()
        ->and($workflow->tasks->firstWhere('key', 'images')->started_at)->toBeNull();
});

it('is idempotent against duplicate job delivery', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);
    $story = $workflow->tasks()->where('key', 'story')->first();

    // The task is awaiting approval (not queued): a duplicate delivery must be a no-op.
    RunWorkflowTask::dispatchSync($story->id);

    expect($story->refresh()->attempts)->toBe(1);
});

it('scopes workflows to their owning project', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    $workflow = app(WorkflowEngine::class)->start($project);

    expect($workflow->project->id)->toBe($project->id)
        ->and($project->workflows()->count())->toBe(1);
});

it('rejects unknown templates', function () {
    $user = User::factory()->create();
    $project = projectFor($user);

    app(WorkflowEngine::class)->start($project, 'does_not_exist');
})->throws(InvalidArgumentException::class);
