<?php

use App\Ai\Adapters\EchoAdapter;
use App\Ai\Adapters\FalAdapter;
use App\Email\TemplateRenderer;
use App\Enums\ProviderStatus;
use App\Jobs\SendTemplatedEmail;
use App\Models\AdminAuditLog;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Settings\SystemSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->member = User::factory()->create(['password' => 'member-password-123']);
});

// Users

it('creates a user from the admin area with a role', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/users', [
            'name' => 'New Person',
            'email' => 'New@Example.com',
            'password' => 'a-long-password-1',
            'role' => 'super_admin',
        ])
        ->assertCreated()
        ->assertJsonPath('role', 'super_admin')
        ->assertJsonPath('email', 'new@example.com');

    expect(User::where('email', 'new@example.com')->value('role'))->toBe('super_admin');
});

it('rejects a duplicate email and a short password when creating', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => $this->member->email, 'password' => 'short', 'role' => 'user'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('edits a user name and email', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$this->member->id}", ['name' => 'Renamed', 'email' => 'renamed@example.com'])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed')
        ->assertJsonPath('email', 'renamed@example.com');

    expect(AdminAuditLog::where('action', 'user.updated')->exists())->toBeTrue();
});

it('soft deletes a user, who can no longer sign in, and restores them', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/admin/users/{$this->member->id}")
        ->assertOk();

    expect(User::query()->find($this->member->id))->toBeNull()
        ->and(User::withTrashed()->find($this->member->id))->not->toBeNull();

    $this->postJson('/api/v1/login', ['email' => $this->member->email, 'password' => 'member-password-123'])
        ->assertUnprocessable();

    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/users/{$this->member->id}/restore")
        ->assertOk()
        ->assertJsonPath('deleted', false);

    expect(User::find($this->member->id))->not->toBeNull();
});

it('lists deleted users under the deleted filter', function () {
    $this->member->delete();

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/users?status=deleted')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.deleted', true);

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/users')
        ->assertJsonCount(1, 'data');
});

it('will not delete your own account or the last super admin', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/admin/users/{$this->owner->id}")
        ->assertUnprocessable();

    expect(User::find($this->owner->id))->not->toBeNull();
});

it('emails a user when their account is suspended', function () {
    Queue::fake();

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$this->member->id}", ['suspended' => true])
        ->assertOk();

    Queue::assertPushed(SendTemplatedEmail::class, fn ($job) => $job->templateKey === 'account_suspended' && $job->to === $this->member->email);
});

it('emails a user when credit is added', function () {
    Queue::fake();

    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/users/{$this->member->id}/credits", ['amount_usd' => 5, 'note' => 'Launch credit'])
        ->assertOk();

    Queue::assertPushed(SendTemplatedEmail::class, fn ($job) => $job->templateKey === 'credit_granted' && $job->data['amount'] === '$5.00');
});

// Providers

it('lists providers and reports the key as configured without showing it', function () {
    config(['virgoai.credentials.FAL_KEY' => 'secret-fal-value']);
    $provider = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'credentials_key' => 'FAL_KEY', 'priority' => 100]);
    AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'fal-ai/gpt-image-1.5', 'name' => 'GPT Image', 'enabled' => true, 'priority' => 100]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/providers')
        ->assertOk()
        ->assertJsonPath('data.1.key', 'fal')
        ->assertJsonPath('data.1.key_configured', true)
        ->assertJsonPath('data.1.models.0.key', 'fal-ai/gpt-image-1.5');

    expect($response->getContent())->not->toContain('secret-fal-value');
});

it('pauses a provider and disables one of its models', function () {
    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/providers/anthropic', ['status' => 'paused'])
        ->assertOk()
        ->assertJsonPath('status', 'paused');

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/providers/anthropic/models/{$model->id}", ['enabled' => false])
        ->assertOk();

    expect($provider->refresh()->status)->toBe(ProviderStatus::Paused)
        ->and($model->refresh()->enabled)->toBeFalse();
});

it('rejects an unknown provider status', function () {
    AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/providers/anthropic', ['status' => 'exploded'])
        ->assertUnprocessable();
});

// Email

it('creates, edits and deletes an email template', function () {
    $created = $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/email/templates', [
            'key' => 'render_done',
            'name' => 'Render done',
            'subject' => 'Your video is ready',
            'body' => 'Hi {{name}}, it is ready.',
            'enabled' => true,
        ])
        ->assertCreated()
        ->json();

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/email/templates/{$created['id']}", ['subject' => 'Ready', 'body' => 'Hi {{name}}', 'name' => 'Render done', 'enabled' => false, 'key' => 'render_done'])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/admin/email/templates/{$created['id']}")
        ->assertOk();

    expect(EmailTemplate::where('key', 'render_done')->exists())->toBeFalse();
});

it('rejects a template key with spaces or capitals', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/email/templates', ['key' => 'Bad Key', 'name' => 'X', 'subject' => 'X', 'body' => 'X', 'enabled' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

it('sends a template with its placeholders filled in', function () {
    Mail::fake();

    (new SendTemplatedEmail('credit_granted', 'member@example.com', ['name' => 'Ada', 'amount' => '$5.00', 'balance' => '$7.00']))->handle(app(TemplateRenderer::class));

    expect(EmailLog::where('status', 'sent')->where('to_email', 'member@example.com')->exists())->toBeTrue();
});

it('does not send a disabled template, and logs it as skipped', function () {
    Mail::fake();
    EmailTemplate::where('key', 'welcome')->update(['enabled' => false]);

    (new SendTemplatedEmail('welcome', 'member@example.com', ['name' => 'Ada']))->handle(app(TemplateRenderer::class));

    Mail::assertNothingSent();
    expect(EmailLog::where('status', 'skipped')->exists())->toBeTrue();
});

it('queues a test send to the owner', function () {
    Queue::fake();

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/email/templates/'.EmailTemplate::where('key', 'welcome')->value('id').'/test')
        ->assertOk();

    Queue::assertPushed(SendTemplatedEmail::class, fn ($job) => $job->to === $this->owner->email);
});

it('sends a welcome email and grants the welcome credit on sign-up', function () {
    Queue::fake();
    SystemSettings::update(['welcome_credit_usd' => 2]);

    $this->postJson('/api/v1/register', [
        'name' => 'Newcomer',
        'email' => 'newcomer@example.com',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertCreated()->assertJsonPath('user.balance_usd', 2);

    Queue::assertPushed(SendTemplatedEmail::class, fn ($job) => $job->templateKey === 'welcome');
});

// System

it('closes sign-ups when registration is turned off', function () {
    SystemSettings::update(['registration_enabled' => false]);

    $this->postJson('/api/v1/register', [
        'name' => 'Late', 'email' => 'late@example.com', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1',
    ])->assertForbidden();

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse();
});

it('stops runs for users during maintenance, but not for the owner', function () {
    SystemSettings::update(['maintenance_mode' => true, 'maintenance_message' => 'Back at 6pm.']);

    $this->actingAs($this->member, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'skill' => 'plain'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Back at 6pm.');

    $ownerResponse = $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'skill' => 'plain']);

    expect($ownerResponse->status())->not->toBe(503);
});

it('saves system settings and audits the change', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/system', ['registration_enabled' => false, 'email_from_name' => 'virgoAi Team'])
        ->assertOk()
        ->assertJsonPath('settings.registration_enabled', false)
        ->assertJsonPath('settings.email_from_name', 'virgoAi Team');

    expect(AdminAuditLog::where('action', 'system.updated')->exists())->toBeTrue();
});

it('hides system settings from non-admins', function () {
    $this->actingAs($this->member, 'sanctum')->getJson('/api/v1/admin/system')->assertNotFound();
});

it('lists every registered provider even before its first sync', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/providers')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'anthropic')
        ->assertJsonPath('data.0.status', 'not_synced')
        ->assertJsonPath('data.0.id', null);
});

it('removes a provider left in the database that no adapter serves', function () {
    AiProvider::create(['key' => 'openai', 'name' => 'OpenAI', 'status' => 'active', 'priority' => 100]);

    $this->artisan('ai:sync')->assertExitCode(1);

    expect(AiProvider::where('key', 'openai')->exists())->toBeFalse();
});
