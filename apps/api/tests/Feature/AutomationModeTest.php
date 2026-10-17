<?php

use App\Models\Project;
use App\Models\User;
use App\Workflows\WorkflowTemplates;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
});

function startGenerative(string $mode): array
{
    return test()->actingAs(test()->user, 'sanctum')
        ->postJson("/api/v1/projects/".test()->project->id."/start", [
            'template_key' => WorkflowTemplates::GENERATIVE_VIDEO,
            'mode' => $mode,
        ])
        ->assertOk()
        ->json('data');
}

/** Key of the stage waiting on the first pending approval, or null. */
function pendingGateKey(array $workflow): ?string
{
    $approval = collect($workflow['approvals'])->firstWhere('status', 'pending');
    if (! $approval) {
        return null;
    }

    return collect($workflow['tasks'])->firstWhere('id', $approval['workflow_task_id'])['key'];
}

it('guided mode stops at every gate, starting with the story', function () {
    $workflow = startGenerative('guided');

    expect($workflow['status'])->toBe('awaiting_approval');
    expect(pendingGateKey($workflow))->toBe('story');
});

it('assisted mode skips the cheap gates and stops at the storyboard', function () {
    $workflow = startGenerative('assisted');

    expect($workflow['status'])->toBe('awaiting_approval');
    expect(pendingGateKey($workflow))->toBe('keyframes');
});

it('assisted mode then stops at the final review, and completes after it', function () {
    $workflow = startGenerative('assisted');
    $approvalId = collect($workflow['approvals'])->firstWhere('status', 'pending')['id'];

    $afterStoryboard = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/approve", [])
        ->assertOk()
        ->json('data');
    expect(pendingGateKey($afterStoryboard))->toBe('final_review');
    $approvalId = collect($afterStoryboard['approvals'])->firstWhere('status', 'pending')['id'];

    $done = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/approve", [])
        ->assertOk()
        ->json('data');
    expect($done['status'])->toBe('completed');
    expect($done['approvals'])->toHaveCount(2);
});

it('full handoff runs to completion with no approvals', function () {
    $workflow = startGenerative('full_handoff');

    expect($workflow['status'])->toBe('completed');
    expect($workflow['approvals'])->toBeEmpty();
    expect(collect($workflow['tasks'])->every(fn ($task) => $task['status'] === 'completed'))->toBeTrue();
    expect($workflow['progress']['percent'])->toBe(100);
});

it('reports stages as automatic in full handoff and as approval-gated in guided', function () {
    $handoff = startGenerative('full_handoff');
    expect(collect($handoff['tasks'])->where('requires_approval', true))->toBeEmpty();

    $guided = startGenerative('guided');
    expect(collect($guided['tasks'])->where('requires_approval', true)->count())->toBe(8);
});
