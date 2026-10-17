<?php

use App\Models\AdminAuditLog;
use App\Models\Generation;
use App\Models\ProviderSecret;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->member = User::factory()->create();
    config(['virgoai.credentials.FAL_KEY' => null, 'virgoai.credentials.ANTHROPIC_API_KEY' => null]);
});

it('saves a key encrypted, and never returns it', function () {
    $response = $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/FAL_KEY', ['value' => 'fal-super-secret-value-1234'])
        ->assertOk()
        ->assertJsonPath('source', 'database')
        ->assertJsonPath('last4', '1234');

    expect($response->getContent())->not->toContain('fal-super-secret-value');

    // Stored encrypted: the raw column is not the plaintext.
    $raw = \Illuminate\Support\Facades\DB::table('provider_secrets')->where('name', 'FAL_KEY')->value('value');
    expect($raw)->not->toContain('fal-super-secret-value')
        ->and(ProviderSecret::where('name', 'FAL_KEY')->first()->value)->toBe('fal-super-secret-value-1234');
});

it('prefers a saved key over the environment, and falls back when it is removed', function () {
    config(['virgoai.credentials.FAL_KEY' => 'env-value-from-server']);

    expect(ProviderCredentials::source('FAL_KEY'))->toBe('environment');

    $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/FAL_KEY', ['value' => 'saved-value-from-console'])
        ->assertOk();

    expect(ProviderCredentials::get('FAL_KEY'))->toBe('saved-value-from-console');

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson('/api/v1/admin/credentials/FAL_KEY')
        ->assertOk()
        ->assertJsonPath('source', 'environment');

    expect(ProviderCredentials::get('FAL_KEY'))->toBe('env-value-from-server');
});

it('reports a key as missing when neither the console nor the server has one', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/credentials')
        ->assertOk()
        ->assertJsonPath('data.0.source', 'missing')
        ->assertJsonPath('data.0.configured', false);
});

it('rejects a key with spaces or that is too short', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/FAL_KEY', ['value' => 'has spaces in it here'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');

    $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/FAL_KEY', ['value' => 'short'])
        ->assertUnprocessable();
});

it('404s on a name that is not a platform key', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/DATABASE_URL', ['value' => 'something-long-enough-1'])
        ->assertNotFound();
});

it('audits saving and removing a key without recording its value', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->putJson('/api/v1/admin/credentials/ANTHROPIC_API_KEY', ['value' => 'sk-ant-audit-check-9999'])
        ->assertOk();

    $log = AdminAuditLog::where('action', 'credential.saved')->firstOrFail();

    expect($log->details)->toBe(['name' => 'ANTHROPIC_API_KEY'])
        ->and(json_encode($log->toArray()))->not->toContain('sk-ant-audit-check-9999');
});

it('hides the key endpoints from non-admins', function () {
    $this->actingAs($this->member, 'sanctum')->getJson('/api/v1/admin/credentials')->assertNotFound();
    $this->actingAs($this->member, 'sanctum')->putJson('/api/v1/admin/credentials/FAL_KEY', ['value' => 'x-long-enough-value'])->assertNotFound();
});

it('tests a saved Anthropic key against the provider', function () {
    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-test-value-0001', $this->owner);

    Http::fake(['api.anthropic.com/v1/models*' => Http::response(['data' => []], 200)]);

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/credentials/ANTHROPIC_API_KEY/test')
        ->assertOk()
        ->assertJsonPath('ok', true);

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'sk-ant-test-value-0001'));
});

it('reports a rejected key with the provider message', function () {
    ProviderCredentials::set('FAL_KEY', 'fal-bad-key-value-0002', $this->owner);

    Http::fake(['api.fal.ai/*' => Http::response(['detail' => 'Invalid API key'], 401)]);

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/credentials/FAL_KEY/test')
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'Invalid API key');
});

it('uses a saved key for the adapters, so runs work without a server key', function () {
    ProviderCredentials::set('FAL_KEY', 'fal-console-key-0003', $this->owner);

    expect((new \App\Ai\Adapters\FalAdapter)->isConfigured())->toBeTrue();
});

it('returns a daily series that covers the whole range, zero-filled', function () {
    Generation::create([
        'user_id' => $this->member->id, 'tool' => 'image', 'capability' => 'image', 'provider_key' => 'fal',
        'model_key' => 'fal-ai/gpt-image-1.5', 'prompt' => 'a fox', 'options' => [], 'status' => 'succeeded',
        'output_type' => 'image', 'price_micros' => 47_600, 'cost_micros' => 34_000,
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/overview?range=7')
        ->assertOk()
        ->assertJsonPath('range', 7)
        ->assertJsonCount(7, 'series')
        ->assertJsonPath('kpis.runs', 1)
        ->assertJsonPath('kpis.revenue_usd', 0.0476)
        ->assertJsonPath('by_tool.0.tool', 'image')
        ->assertJsonPath('by_model.0.model', 'fal-ai/gpt-image-1.5');

    expect(collect($response->json('series'))->sum('runs'))->toBe(1);
});

it('rejects an unsupported dashboard range', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/overview?range=5')
        ->assertUnprocessable();
});
