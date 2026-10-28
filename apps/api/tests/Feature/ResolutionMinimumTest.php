<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['balance_micros' => 50_000_000]);
});

it('refuses a video tool resolution below 720p before any generation', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/video/run', ['prompt' => 'A fox crosses a frozen lake', 'resolution' => '480p'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['resolution']);
});

it('refuses 480p for a production and a film as well', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/productions', ['prompt' => 'A rainy evening', 'format' => 'landscape_16_9', 'quality' => '480p'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['quality']);
});
