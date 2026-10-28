<?php

use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowTask;
use App\Workflows\WorkflowTemplates;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id]);
});

it('starts a workflow for a project', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $response->assertOk()
        ->assertJsonPath('data.status', 'awaiting_approval')
        ->assertJsonCount(4, 'data.tasks')
        ->assertJsonCount(1, 'data.approvals');
});

it('returns the task dependency edges with the workflow', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start")
        ->assertOk();

    $taskIds = collect($response->json('data.tasks'))->pluck('id');
    $edges = collect($response->json('data.dependencies'));

    expect($edges)->not->toBeEmpty()
        ->and($edges->every(fn ($edge) => $taskIds->contains($edge['task_id'])
            && $taskIds->contains($edge['depends_on_task_id'])))->toBeTrue();
});

it('rejects an invalid automation mode', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", ['mode' => 'turbo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('mode');
});

it('shows the latest workflow for a project', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/projects/{$this->project->id}/workflow")
        ->assertOk()
        ->assertJsonCount(4, 'data.tasks')
        ->assertJsonPath('data.progress.total', 4);
});

it('returns 404 when no workflow has been started', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/projects/{$this->project->id}/workflow")
        ->assertNotFound();
});

it('approves a checkpoint and completes the workflow', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start")
        ->assertOk();

    $approvalId = $start->json('data.approvals.0.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/approve", ['notes' => 'Go'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('revises a checkpoint through the API', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $approvalId = $start->json('data.approvals.0.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/revise", [
            'notes' => 'Reworded',
            'revision' => ['summary' => 'User edit'],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('cancels a running workflow', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $workflowId = $start->json('data.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/workflows/{$workflowId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('forbids another user from starting a workflow on the project', function () {
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start")
        ->assertForbidden();
});

it('forbids another user from viewing the workflow', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'sanctum')
        ->getJson("/api/v1/projects/{$this->project->id}/workflow")
        ->assertForbidden();
});

it('forbids another user from deciding an approval', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $approvalId = $start->json('data.approvals.0.id');
    $intruder = User::factory()->create();

    $this->actingAs($intruder, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/approve")
        ->assertForbidden();
});

it('requires authentication for workflow endpoints', function () {
    $this->postJson("/api/v1/projects/{$this->project->id}/start")->assertUnauthorized();
    $this->getJson("/api/v1/projects/{$this->project->id}/workflow")->assertUnauthorized();
});

it('sets instructions for a pending model stage', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => 'content_pipeline',
        ])
        ->assertOk();

    $task = collect($start->json('data.tasks'))->firstWhere('key', 'script');
    expect($task['prompt_editable'])->toBeTrue();

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$task['id']}", [
            'prompt' => '  Keep the script under 60 seconds.  ',
        ])
        ->assertOk();

    expect(WorkflowTask::find($task['id'])->input['prompt'])->toBe('Keep the script under 60 seconds.');
});

it('restores the template instructions when the prompt is cleared', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => 'content_pipeline',
        ]);
    $task = collect($start->json('data.tasks'))->firstWhere('key', 'script');
    $default = $task['prompt'];

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$task['id']}", ['prompt' => 'Short'])
        ->assertOk();
    $cleared = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$task['id']}", ['prompt' => ' '])
        ->assertOk();

    $restored = collect($cleared->json('data.tasks'))->firstWhere('key', 'script');
    expect($restored['prompt'])->toBe($default)
        ->and(WorkflowTask::find($task['id'])->input['prompt'])->toBe($default);
});

it('refuses instructions for a stage that has already started', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => 'content_pipeline',
        ]);
    $story = collect($start->json('data.tasks'))->firstWhere('key', 'story');

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$story['id']}", ['prompt' => 'Too late'])
        ->assertStatus(409);
});

it('refuses instructions for a stage that does not run a model', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");
    $task = collect($start->json('data.tasks'))->firstWhere('key', 'script');

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$task['id']}", ['prompt' => 'Nope'])
        ->assertStatus(422);
});

it('does not let another user change a run', function () {
    $start = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => 'content_pipeline',
        ]);
    $task = collect($start->json('data.tasks'))->firstWhere('key', 'script');

    $stranger = User::factory()->create();
    $this->actingAs($stranger, 'sanctum')
        ->patchJson("/api/v1/workflows/{$start->json('data.id')}/tasks/{$task['id']}", ['prompt' => 'Mine now'])
        ->assertForbidden();
});

