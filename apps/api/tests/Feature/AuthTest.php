<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@virgoai.test',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $this->assertDatabaseHas('users', ['email' => 'ada@virgoai.test']);
});

it('rejects duplicate emails on registration', function () {
    User::factory()->create(['email' => 'taken@virgoai.test']);

    $this->postJson('/api/v1/register', [
        'name' => 'Someone',
        'email' => 'taken@virgoai.test',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('logs in with valid credentials', function () {
    User::factory()->create([
        'email' => 'grace@virgoai.test',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'grace@virgoai.test',
        'password' => 'secret-password',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'grace@virgoai.test',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/login', [
        'email' => 'grace@virgoai.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('blocks unauthenticated access to projects', function () {
    $this->getJson('/api/v1/projects')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
