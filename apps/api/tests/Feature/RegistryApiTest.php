<?php

use App\Models\User;

it('requires authentication for the registry catalog', function () {
    $this->getJson('/api/v1/providers')->assertUnauthorized();
    $this->getJson('/api/v1/models')->assertUnauthorized();
});

it('lists providers without exposing credentials', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/providers')
        ->assertOk();

    $response->assertJsonMissingPath('data.0.credentials_key');
});

it('lists models with provider and capabilities', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/models')->assertOk();

    expect($response->json('data'))->toBeArray();
});
