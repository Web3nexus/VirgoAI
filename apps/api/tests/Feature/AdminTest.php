<?php

use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\LedgerEntry;
use App\Models\ModelPrice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'password' => 'owner-password-123']);
    $this->member = User::factory()->create(['password' => 'member-password-123']);
});

it('answers 404 to a signed-in user who is not a super admin', function () {
    $this->actingAs($this->member, 'sanctum')
        ->getJson('/api/v1/admin/overview')
        ->assertNotFound();

    $this->actingAs($this->member, 'sanctum')
        ->getJson('/api/v1/admin/users')
        ->assertNotFound();
});

it('answers 401 to a guest on the admin API', function () {
    $this->getJson('/api/v1/admin/overview')->assertUnauthorized();
});

it('signs a super admin in at /securegate and writes an audit entry', function () {
    $this->postJson('/api/v1/admin/login', ['email' => $this->owner->email, 'password' => 'owner-password-123'])
        ->assertOk()
        ->assertJsonPath('user.is_super_admin', true)
        ->assertJsonStructure(['token']);

    expect(AdminAuditLog::where('action', 'admin.login')->count())->toBe(1);
});

it('gives the same answer for a wrong password, a non-admin, and an unknown email', function () {
    $wrong = $this->postJson('/api/v1/admin/login', ['email' => $this->owner->email, 'password' => 'nope-nope-nope'])->assertUnauthorized();
    $nonAdmin = $this->postJson('/api/v1/admin/login', ['email' => $this->member->email, 'password' => 'member-password-123'])->assertUnauthorized();
    $unknown = $this->postJson('/api/v1/admin/login', ['email' => 'nobody@example.com', 'password' => 'whatever-123'])->assertUnauthorized();

    expect($wrong->json('message'))->toBe('Invalid credentials.')
        ->and($nonAdmin->json('message'))->toBe('Invalid credentials.')
        ->and($unknown->json('message'))->toBe('Invalid credentials.');
});

it('blocks a suspended user at login and on an existing token', function () {
    $token = $this->member->createToken('web')->plainTextToken;
    $this->member->forceFill(['suspended_at' => now()])->save();

    $this->postJson('/api/v1/login', ['email' => $this->member->email, 'password' => 'member-password-123'])
        ->assertForbidden();

    $this->withToken($token)->getJson('/api/v1/user')->assertForbidden();
});

it('lists users with a search and their balance', function () {
    User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'balance_micros' => 2_500_000]);

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/users?q=ada')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'ada@example.com')
        ->assertJsonPath('data.0.balance_usd', 2.5);
});

it('promotes and suspends another user, and audits both', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$this->member->id}", ['role' => User::ROLE_SUPER_ADMIN, 'suspended' => true])
        ->assertOk()
        ->assertJsonPath('role', User::ROLE_SUPER_ADMIN)
        ->assertJsonPath('suspended', true);

    expect(AdminAuditLog::where('action', 'user.updated')->count())->toBe(1);
});

it('will not let the owner demote or suspend their own account', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$this->owner->id}", ['role' => User::ROLE_USER])
        ->assertUnprocessable();

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$this->owner->id}", ['suspended' => true])
        ->assertUnprocessable();
});

it('lets one super admin demote another', function () {
    $other = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/users/{$other->id}", ['role' => User::ROLE_USER])
        ->assertOk()
        ->assertJsonPath('role', User::ROLE_USER);
});

it('adds credit with a ledger entry and an audit entry', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/users/{$this->member->id}/credits", ['amount_usd' => 10, 'note' => 'Launch credit'])
        ->assertOk()
        ->assertJsonPath('balance_usd', 10);

    expect(LedgerEntry::where('user_id', $this->member->id)->where('type', LedgerEntry::TYPE_GRANT)->value('amount_micros'))->toBe(10_000_000)
        ->and(AdminAuditLog::where('action', 'user.credit')->exists())->toBeTrue();
});

it('removes credit, but not below zero', function () {
    $this->member->forceFill(['balance_micros' => 1_000_000])->save();

    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/users/{$this->member->id}/credits", ['amount_usd' => -2, 'note' => 'Too much'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount_usd');

    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/users/{$this->member->id}/credits", ['amount_usd' => -0.5, 'note' => 'Refund for error'])
        ->assertOk()
        ->assertJsonPath('balance_usd', 0.5);
});

it('shows a user their ledger and recent runs to the owner', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/v1/admin/users/{$this->member->id}")
        ->assertOk()
        ->assertJsonStructure(['totals', 'recent_runs', 'ledger']);
});

it('reports the margin, what was paid and what it cost on the overview', function () {
    AppSetting::write('margin_percent', 40);

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/overview')
        ->assertOk()
        ->assertJsonPath('margin_percent', 40)
        ->assertJsonPath('kept_share_percent', 28.57)
        ->assertJsonPath('revenue_usd', 0);
});

it('changes the margin and audits the change', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/pricing/margin', ['margin_percent' => 60])
        ->assertOk()
        ->assertJsonPath('margin_percent', 60)
        ->assertJsonPath('kept_share_percent', 37.5);

    expect(AdminAuditLog::where('action', 'pricing.margin')->value('details'))->toBe(['from' => 40, 'to' => 60]);
});

it('sets a price by hand and keeps it from later syncs', function () {
    $price = ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/gpt-image-1.5', 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson("/api/v1/admin/pricing/prices/{$price->id}", ['cost_usd' => 0.05])
        ->assertOk()
        ->assertJsonPath('source', 'admin')
        ->assertJsonPath('cost_usd', 0.05);

    expect($price->refresh()->cost_micros)->toBe(50_000);
});

it('lists the prices with the sell price after margin', function () {
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/gpt-image-1.5', 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/pricing')
        ->assertOk()
        ->assertJsonPath('prices.0.cost_usd', 0.034)
        ->assertJsonPath('prices.0.sell_usd', 0.0476);
});

it('shows the audit trail to the owner only', function () {
    AdminAuditLog::record($this->owner, 'pricing.margin', null, ['from' => 40, 'to' => 60]);

    $this->actingAs($this->member, 'sanctum')->getJson('/api/v1/admin/audit')->assertNotFound();

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/audit')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'pricing.margin');
});

it('creates the super admin from the command line', function () {
    $this->artisan('admin:create', ['email' => 'Founder@Example.com', '--name' => 'Founder'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-password-1')
        ->expectsQuestion('Repeat the password', 'a-long-password-1')
        ->assertExitCode(0);

    $user = User::where('email', 'founder@example.com')->firstOrFail();

    expect($user->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('a-long-password-1', $user->password))->toBeTrue();
});

it('refuses a super admin password shorter than 12 characters', function () {
    $this->artisan('admin:create', ['email' => 'short@example.com', '--name' => 'Short'])
        ->expectsQuestion('Password (at least 12 characters)', 'short')
        ->assertExitCode(1);

    expect(User::where('email', 'short@example.com')->exists())->toBeFalse();
});

it('promotes an existing user to super admin from the command line', function () {
    $this->artisan('admin:create', ['email' => $this->member->email])
        ->expectsConfirmation("{$this->member->email} already has an account. Make it a super admin?", 'yes')
        ->assertExitCode(0);

    expect($this->member->refresh()->isSuperAdmin())->toBeTrue();
});
