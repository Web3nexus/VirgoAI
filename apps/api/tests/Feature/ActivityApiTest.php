<?php

use App\Models\Project;
use App\Models\User;
use App\Workflows\WorkflowTemplates;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'My film']);
});

it('starts the generative video pipeline with its MVP stages', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => WorkflowTemplates::GENERATIVE_VIDEO,
        ])
        ->assertOk()
        ->assertJsonPath('data.template_key', 'generative_video')
        ->assertJsonCount(11, 'data.tasks');

    $keys = collect($response->json('data.tasks'))->pluck('key')->all();
    expect($keys)->toContain('characters', 'environments', 'keyframes', 'motion', 'narration', 'thumbnail');
});

it('marks stages that need approval as gated', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => WorkflowTemplates::GENERATIVE_VIDEO,
        ]);

    $gated = collect($response->json('data.tasks'))->where('requires_approval', true)->pluck('key')->all();
    expect($gated)->toEqual(['story', 'script', 'characters', 'environments', 'scenes', 'keyframes', 'final_review', 'thumbnail']);
});

it('labels placeholder stage output as placeholder', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start", [
            'template_key' => WorkflowTemplates::GENERATIVE_VIDEO,
        ]);
    $approvalId = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/projects/{$this->project->id}/workflow")
        ->json('data.approvals.0.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/approvals/{$approvalId}/approve", [])
        ->assertOk();

    $tasks = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/projects/{$this->project->id}/workflow")
        ->json('data.tasks');

    $story = collect($tasks)->firstWhere('key', 'story');
    expect($story['output']['placeholder'])->toBeTrue();
    expect($story['finished_at'])->not->toBeNull();
});

it('lists recent activity across the user projects with project titles', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/projects/{$this->project->id}/start");

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.project.title', 'My film')
        ->assertJsonPath('data.0.status', 'awaiting_approval')
        ->assertJsonCount(4, 'data.0.tasks');
});

it('never shows another user activity', function () {
    $other = User::factory()->create();
    $otherProject = Project::factory()->create(['user_id' => $other->id]);
    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/projects/{$otherProject->id}/start");

    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('requires authentication for activity', function () {
    $this->getJson('/api/v1/activity')->assertUnauthorized();
});
