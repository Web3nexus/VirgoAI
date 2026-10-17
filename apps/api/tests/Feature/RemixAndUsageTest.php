<?php

use App\Ai\GenerationService;
use App\Billing\PriceQuoter;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Clips\Originality;
use App\Clips\Remix as RemixPrompt;
use App\Jobs\ProcessRemix;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\ModelPrice;
use App\Models\ProviderCapability;
use App\Models\Remix;
use App\Models\UsageEvent;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const SOURCE_TEXT = 'Welcome to the pricing talk. Our plan costs nineteen dollars a month and you can cancel anytime you want without any penalty.';

beforeEach(function () {
    $this->user = User::factory()->create(['balance_micros' => 50_000_000]);
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    ProviderCredentials::set('ANTHROPIC_API_KEY', 'sk-ant-remix-key-0001', $this->owner);
    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-haiku-5-5', 'name' => 'Haiku', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok', 'cost_micros' => 100_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'output_mtok', 'cost_micros' => 500_000, 'source' => 'anthropic_docs']);

    $this->source = ClipSource::create([
        'user_id' => $this->user->id, 'kind' => 'upload', 'status' => 'ready', 'duration_seconds' => 20,
        'transcript' => [['start' => 0, 'end' => 10, 'text' => 'Welcome to the pricing talk.'], ['start' => 10, 'end' => 20, 'text' => 'Our plan costs nineteen dollars a month and you can cancel anytime you want without any penalty.']],
    ]);
});

function haikuReply(array $story): array
{
    return [
        'content' => [['type' => 'text', 'text' => json_encode($story)]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 500, 'output_tokens' => 300],
    ];
}

// The copy guard

it('finds copied runs of eight words, ignoring case and punctuation', function () {
    $copied = 'He said: our plan costs nineteen dollars a month, and you can cancel anytime you want without any penalty!';

    expect(Originality::sharedRuns($copied, SOURCE_TEXT))->toBeGreaterThan(0);
});

it('accepts a story written in new words, even when it follows the same events', function () {
    $fresh = 'A small studio in the harbour town counts its monthly bills and finds the subscription fee small enough to try. '
        .'Nobody is locked in, and the founder leaves the plan the moment it stops helping.';

    expect(Originality::sharedRuns($fresh, SOURCE_TEXT))->toBe(0);
});

// The rewrite

it('writes an original story, records its tokens, and marks it original', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$this->source->id}/remixes", ['instructions' => 'A harbour town tale'])
        ->assertCreated()
        ->assertJsonPath('status', 'queued');

    $remix = Remix::firstOrFail();

    Http::fake(['api.anthropic.com/v1/messages' => Http::response(haikuReply([
        'title' => 'The Harbour Ledger',
        'logline' => 'A café owner tests a small monthly fee.',
        'story' => 'Every month the café owner totals the bills by hand. One evening a new tool offers a plan that costs less than lunch. She tries it, finds she can leave whenever she wants, and stays.',
    ]))]);

    (new ProcessRemix($remix->id))->handle(app(GenerationService::class), app(PriceQuoter::class), app(Wallet::class), app(UsageRecorder::class));

    $remix->refresh();
    expect($remix->status)->toBe('succeeded')
        ->and($remix->title)->toBe('The Harbour Ledger')
        ->and($remix->shared_runs)->toBe(0);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/remixes/{$remix->id}")
        ->assertOk()
        ->assertJsonPath('original', true)
        ->assertJsonPath('usage.total_tokens', 800)
        ->assertJsonPath('usage.steps.0.step', 'rewrite');
});

it('refuses a rewrite that copies the source twice, and refunds the charge', function () {
    Queue::fake();
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$this->source->id}/remixes", [])
        ->assertCreated();

    $remix = Remix::firstOrFail();
    $before = $this->user->refresh()->balance_micros;

    $copy = ['title' => 'Copy', 'logline' => 'x', 'story' => SOURCE_TEXT];
    Http::fake(['api.anthropic.com/v1/messages' => Http::sequence()
        ->push(haikuReply($copy))
        ->push(haikuReply($copy)),
    ]);

    (new ProcessRemix($remix->id))->handle(app(GenerationService::class), app(PriceQuoter::class), app(Wallet::class), app(UsageRecorder::class));

    expect($remix->refresh()->status)->toBe('failed')
        ->and($remix->error)->toContain('refunded')
        ->and($this->user->refresh()->balance_micros)->toBeGreaterThan($before)
        ->and(Generation::find($remix->generation_id)->status)->toBe('failed');
});

it('refuses a source with no transcript', function () {
    $empty = ClipSource::create(['user_id' => $this->user->id, 'kind' => 'youtube', 'status' => 'ready', 'youtube_id' => 'dQw4w9WgXcQ']);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/clip-sources/{$empty->id}/remixes", [])
        ->assertUnprocessable();
});

// Usage

it('gives the user tokens and credits per step, and never the provider cost', function () {
    $remix = Remix::create(['user_id' => $this->user->id, 'clip_source_id' => $this->source->id, 'model_key' => 'claude-haiku-5-5', 'status' => 'succeeded', 'story' => 'x', 'shared_runs' => 0]);

    UsageEvent::create([
        'user_id' => $this->user->id, 'production_type' => 'remix', 'production_id' => $remix->id, 'step' => 'rewrite',
        'provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'input_tokens' => 500, 'output_tokens' => 300,
        'units' => 1, 'unit_label' => 'rewrite', 'cost_micros' => 100, 'price_micros' => 140,
    ]);
    UsageEvent::create([
        'user_id' => $this->user->id, 'production_type' => 'remix', 'production_id' => $remix->id, 'step' => 'rewrite_retry',
        'provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'input_tokens' => 100, 'output_tokens' => 50,
        'units' => 1, 'unit_label' => 'rewrite', 'cost_micros' => 10, 'price_micros' => 14,
    ]);

    $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/usage/remix/{$remix->id}")->assertOk();

    expect($response->json('total_tokens'))->toBe(950)
        ->and($response->json('total_platform_tokens'))->toBe(0.2)
        ->and($response->getContent())->not->toContain('cost_usd')
        ->and($response->getContent())->not->toContain('kept');
});

it('gives the owner the cost, the price, and what was kept, per step', function () {
    UsageEvent::create([
        'user_id' => $this->user->id, 'production_type' => 'remix', 'production_id' => 'R2', 'step' => 'rewrite',
        'provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'input_tokens' => 1000, 'output_tokens' => 1000,
        'units' => 1, 'unit_label' => 'rewrite', 'cost_micros' => 600, 'price_micros' => 840,
    ]);

    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/usage/remix/R2')
        ->assertOk()
        ->assertJsonPath('cost_usd', 0.0006)
        ->assertJsonPath('price_usd', 0.00084)
        ->assertJsonPath('kept_usd', 0.00024);

    $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/admin/usage/remix/R2')->assertNotFound();
});

it('does not show one user the usage of another', function () {
    $stranger = User::factory()->create();
    Remix::create(['user_id' => $stranger->id, 'clip_source_id' => $this->source->id, 'model_key' => 'claude-haiku-5-5', 'status' => 'succeeded', 'story' => 'x', 'shared_runs' => 0]);

    $remix = Remix::where('user_id', $stranger->id)->firstOrFail();

    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/usage/remix/{$remix->id}")->assertNotFound();
    $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/remixes/{$remix->id}")->assertNotFound();
});

it('keeps the rewrite instructions short and separate from the transcript', function () {
    expect(RemixPrompt::systemPrompt())->toContain('ORIGINAL')
        ->and(RemixPrompt::parseReply('no json here'))->toBeNull();
});
