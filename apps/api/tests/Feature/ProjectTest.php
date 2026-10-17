<?php

use App\Models\Project;
use App\Models\User;

it('lists only the authenticated user projects', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Project::factory()->count(2)->create(['user_id' => $user->id]);
    Project::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a project owned by the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', [
            'title' => 'My new project',
            'description' => 'Context',
            'type' => 'youtube_url',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'My new project')
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'title' => 'My new project',
    ]);
});

it('validates the project payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/projects', ['type' => 'nonsense'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'type']);
});

it('shows an owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $project->id);
});

it('forbids viewing another user project', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertForbidden();
});

it('updates an owned project status', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('forbids updating another user project', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}", ['status' => 'active'])
        ->assertForbidden();
});

it('soft deletes an owned project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});
