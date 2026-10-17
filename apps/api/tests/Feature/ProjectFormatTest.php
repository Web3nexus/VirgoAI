<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('defaults a new project to landscape 16:9 at 1920x1080', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/projects', ['title' => 'Film', 'type' => 'idea'])
        ->assertCreated()
        ->assertJsonPath('data.format', 'landscape_16_9')
        ->assertJsonPath('data.dimensions.width', 1920)
        ->assertJsonPath('data.dimensions.height', 1080)
        ->assertJsonPath('data.preview_url', null);
});

it('sets vertical 9:16 dimensions for short-form output', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/projects', ['title' => 'Short', 'type' => 'idea', 'format' => 'vertical_9_16'])
        ->assertCreated()
        ->assertJsonPath('data.format_label', 'Vertical 9:16')
        ->assertJsonPath('data.dimensions.width', 1080)
        ->assertJsonPath('data.dimensions.height', 1920);
});

it('rejects an unknown format', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/projects', ['title' => 'Bad', 'type' => 'idea', 'format' => 'cinemascope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('format');
});

it('changes the format of an existing project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->actingAs($this->user, 'sanctum')
        ->putJson("/api/v1/projects/{$project->id}", ['format' => 'square_1_1'])
        ->assertOk()
        ->assertJsonPath('data.dimensions.width', 1080)
        ->assertJsonPath('data.dimensions.height', 1080);
});

it('exposes a preview url once a render exists', function () {
    $project = Project::factory()->create([
        'user_id' => $this->user->id,
        'preview_url' => 'https://cdn.example.test/previews/p1.mp4',
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.preview_url', 'https://cdn.example.test/previews/p1.mp4');
});
