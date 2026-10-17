<?php

use App\Billing\InsufficientCredit;
use App\Billing\Money;
use App\Billing\PriceQuoter;
use App\Billing\PriceSync;
use App\Billing\PriceUnavailable;
use App\Billing\PricingSettings;
use App\Billing\Quote;
use App\Billing\Wallet;
use App\Enums\Capability;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AppSetting;
use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\ModelPrice;
use App\Models\ProviderCapability;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create(['balance_micros' => 0]);
});

function priceRow(string $provider, string $model, string $unit, int $micros, string $source = 'fal_api'): ModelPrice
{
    return ModelPrice::create([
        'provider_key' => $provider,
        'model_key' => $model,
        'unit' => $unit,
        'cost_micros' => $micros,
        'source' => $source,
        'verified_at' => now(),
    ]);
}

it('converts between dollars and micro-dollars without float drift', function () {
    expect(Money::fromUsd(0.034))->toBe(34_000)
        ->and(Money::fromUsd('0.3034'))->toBe(303_400)
        ->and(Money::toUsd(34_000))->toBe(0.034)
        ->and(Money::format(1_500_000))->toBe('$1.50')
        ->and(Money::format(-2_000, 4))->toBe('-$0.0020');
});

it('rounds a cost up so a partial micro is never free', function () {
    expect(Money::times(34_000, 1))->toBe(34_000)
        ->and(Money::tokens(1, 2_000_000))->toBe(2)
        ->and(Money::tokens(1000, 2_000_000))->toBe(2000);
});

it('sells at cost plus the margin, rounded up', function () {
    $settings = new PricingSettings;

    expect($settings->marginPercent())->toBe(40)
        ->and($settings->sell(34_000))->toBe(47_600)
        ->and($settings->sell(1))->toBe(2)
        ->and($settings->keptShare())->toBe(28.57);

    AppSetting::write(PricingSettings::MARGIN_KEY, 100);

    expect($settings->sell(34_000))->toBe(68_000)
        ->and($settings->keptShare())->toBe(50.0);
});

it('prices a fal image run at the fal unit price times the image count', function () {
    priceRow('fal', 'fal-ai/gpt-image-1.5', 'image', 34_000);

    $quote = app(PriceQuoter::class)->quote('fal', 'fal-ai/gpt-image-1.5', Capability::Image, 'a fox', ['size' => 'square_1_1'], []);

    expect($quote->costMicros)->toBe(34_000)
        ->and($quote->priceMicros)->toBe(47_600)
        ->and($quote->unit)->toBe('image');
});

it('prices a Seedance run per second of video', function () {
    priceRow('fal', 'bytedance/seedance-2.0/image-to-video', 'second', 302_400);

    $quote = app(PriceQuoter::class)->quote(
        'fal',
        'bytedance/seedance-2.0/image-to-video',
        Capability::Video,
        'a slow pan',
        ['duration' => 6],
        ['image_url' => 'https://v3b.fal.media/files/k.png'],
    );

    expect($quote->costMicros)->toBe(1_814_400)
        ->and($quote->quantity)->toBe(6.0)
        ->and($quote->unit)->toBe('second');
});

it('refuses a fal unit it cannot bill rather than guessing', function () {
    priceRow('fal', 'fal-ai/gpt-image-1.5', 'gpu-second', 1_000);

    app(PriceQuoter::class)->quote('fal', 'fal-ai/gpt-image-1.5', Capability::Image, 'a fox', [], []);
})->throws(PriceUnavailable::class, 'cannot price yet');

it('refuses a fal model with no price yet', function () {
    app(PriceQuoter::class)->quote('fal', 'fal-ai/gpt-image-1.5', Capability::Image, 'a fox', [], []);
})->throws(PriceUnavailable::class, 'No price is set');

it('refuses a Claude model with no price, and says where to set it', function () {
    app(PriceQuoter::class)->quote('anthropic', 'claude-haiku-5-5', Capability::Text, 'Write a story', [], []);
})->throws(PriceUnavailable::class, 'Pricing page');

it('reserves a Claude run at the upper bound, then settles to the tokens used', function () {
    priceRow('anthropic', 'claude-sonnet-5-5', 'input_mtok', 2_000_000, 'anthropic_docs');
    priceRow('anthropic', 'claude-sonnet-5-5', 'output_mtok', 10_000_000, 'anthropic_docs');

    $quoter = app(PriceQuoter::class);
    $reserve = $quoter->quote('anthropic', 'claude-sonnet-5-5', Capability::Text, str_repeat('a', 300), ['system' => ''], []);

    expect($reserve->estimate)->toBeTrue()
        ->and($reserve->priceMicros)->toBeGreaterThan(0);

    $actual = $quoter->settleText('anthropic', 'claude-sonnet-5-5', ['input_tokens' => 1000, 'output_tokens' => 500]);

    // 1000 input tokens at $2/MTok plus 500 output at $10/MTok is $0.007, sold at +40%.
    expect($actual->costMicros)->toBe(7_000)
        ->and($actual->priceMicros)->toBe(9_800)
        ->and($actual->estimate)->toBeFalse();
});

it('reserves a run from the balance and writes a charge in the ledger', function () {
    $this->user->forceFill(['balance_micros' => 1_000_000])->save();

    $run = app(Wallet::class)->reserveRun($this->user, new Quote(34_000, 47_600, 'image', 1, false), [
        'user_id' => $this->user->id,
        'tool' => 'image',
        'capability' => 'image',
        'provider_key' => 'fal',
        'model_key' => 'fal-ai/gpt-image-1.5',
        'prompt' => 'a fox',
        'options' => [],
        'output_type' => 'image',
    ]);

    $this->user->refresh();

    expect($run->status)->toBe(Generation::STATUS_PENDING)
        ->and($run->price_micros)->toBe(47_600)
        ->and($this->user->balance_micros)->toBe(1_000_000 - 47_600)
        ->and(LedgerEntry::where('generation_id', $run->id)->value('type'))->toBe(LedgerEntry::TYPE_CHARGE);
});

it('refuses a run the balance cannot cover, and creates nothing', function () {
    expect(fn () => app(Wallet::class)->reserveRun($this->user, new Quote(1, 100_000, 'image', 1, false), [
        'user_id' => $this->user->id,
        'tool' => 'image',
        'capability' => 'image',
        'provider_key' => 'fal',
        'model_key' => 'x',
        'prompt' => 'a fox',
        'options' => [],
        'output_type' => 'image',
    ]))->toThrow(InsufficientCredit::class);

    expect(Generation::count())->toBe(0)
        ->and(LedgerEntry::count())->toBe(0);
});

it('refunds a failed run once, however many times it is asked', function () {
    $this->user->forceFill(['balance_micros' => 1_000_000])->save();
    $wallet = app(Wallet::class);

    $run = $wallet->reserveRun($this->user, new Quote(34_000, 47_600, 'image', 1, false), [
        'user_id' => $this->user->id, 'tool' => 'image', 'capability' => 'image', 'provider_key' => 'fal',
        'model_key' => 'x', 'prompt' => 'a fox', 'options' => [], 'output_type' => 'image',
    ]);

    $wallet->refund($run);
    $wallet->refund($run);

    expect($this->user->refresh()->balance_micros)->toBe(1_000_000)
        ->and(LedgerEntry::where('type', LedgerEntry::TYPE_REFUND)->count())->toBe(1)
        ->and($run->refresh()->price_micros)->toBe(0);
});

it('settles a reserved price down to the actual price, and adds the difference back', function () {
    $this->user->forceFill(['balance_micros' => 1_000_000])->save();
    $wallet = app(Wallet::class);

    $run = $wallet->reserveRun($this->user, new Quote(100_000, 140_000, 'tokens', 9000, true), [
        'user_id' => $this->user->id, 'tool' => 'story', 'capability' => 'text', 'provider_key' => 'anthropic',
        'model_key' => 'claude-sonnet-5-5', 'prompt' => 'a fox', 'options' => [], 'output_type' => 'text',
    ]);

    $wallet->settle($run, new Quote(7_000, 9_800, 'tokens', 1500, false));

    expect($this->user->refresh()->balance_micros)->toBe(1_000_000 - 9_800)
        ->and($run->refresh()->price_micros)->toBe(9_800)
        ->and($run->cost_micros)->toBe(7_000);
});

it('will not let an adjustment take the balance below zero', function () {
    $this->user->forceFill(['balance_micros' => 500_000])->save();

    expect(fn () => app(Wallet::class)->adjust($this->user, -600_000, 'Clawback', null))
        ->toThrow(DomainException::class, 'more than the balance');
});

it('keeps an admin-set price through a sync, but refreshes a fal-sourced one', function () {
    config(['virgoai.credentials.FAL_KEY' => 'fal-key']);
    priceRow('fal', 'fal-ai/gpt-image-1.5', 'image', 99_000, 'admin');

    Http::fake(['api.fal.ai/v1/models/pricing*' => Http::response(['prices' => [
        ['endpoint_id' => 'fal-ai/gpt-image-1.5', 'unit_price' => 0.034, 'unit' => 'image', 'currency' => 'USD'],
    ]])]);

    $summary = app(PriceSync::class)->syncFal();

    expect(ModelPrice::where('model_key', 'fal-ai/gpt-image-1.5')->value('cost_micros'))->toBe(99_000)
        ->and($summary['skipped_admin'])->toBe(1);
});

it('reads fal prices for each endpoint and reports any it could not price', function () {
    config(['virgoai.credentials.FAL_KEY' => 'fal-key']);

    Http::fake(['api.fal.ai/v1/models/pricing*' => Http::response(['prices' => [
        ['endpoint_id' => 'fal-ai/gpt-image-1.5', 'unit_price' => 0.034, 'unit' => 'image', 'currency' => 'USD'],
        ['endpoint_id' => 'bytedance/seedance-2.0/image-to-video', 'unit_price' => 0.3034, 'unit' => 'second', 'currency' => 'USD'],
    ]])]);

    $summary = app(PriceSync::class)->syncFal();

    expect($summary['upserted'])->toBe(2)
        ->and($summary['errors'])->toHaveCount(2)
        ->and(implode(' ', $summary['errors']))->toContain('fal-ai/gpt-image-1.5/edit')
        ->and(implode(' ', $summary['errors']))->toContain('fal-ai/whisper');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'endpoint_id=fal-ai%2Fgpt-image-1.5%2Fedit'));
});

it('seeds Anthropic reference prices from config', function () {
    $summary = app(PriceSync::class)->seedAnthropic();

    expect($summary['upserted'])->toBeGreaterThanOrEqual(6)
        ->and(ModelPrice::where(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'input_mtok'])->value('cost_micros'))->toBe(2_000_000)
        ->and(ModelPrice::where(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'output_mtok'])->value('cost_micros'))->toBe(10_000_000)
        ->and(ModelPrice::where(['provider_key' => 'anthropic', 'model_key' => 'claude-haiku-5-5', 'unit' => 'input_mtok'])->value('cost_micros'))->toBe(100_000);
});

it('charges a text run and settles it to the tokens used', function () {
    config(['virgoai.credentials.ANTHROPIC_API_KEY' => 'test-anthropic-key']);
    app(PriceSync::class)->seedAnthropic();
    $this->user->forceFill(['balance_micros' => 5_000_000])->save();

    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'content' => [['type' => 'text', 'text' => 'A fox story.']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
    ])]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'skill' => 'plain', 'model_key' => 'claude-sonnet-5-5'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.price_usd', 0.0098);

    expect($this->user->refresh()->balance_micros)->toBe(5_000_000 - 9_800);
});

it('does not charge a text run that fails, and refunds the reservation', function () {
    config(['virgoai.credentials.ANTHROPIC_API_KEY' => 'test-anthropic-key']);
    app(PriceSync::class)->seedAnthropic();
    $this->user->forceFill(['balance_micros' => 5_000_000])->save();

    $provider = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'text']);

    Http::fake(['api.anthropic.com/v1/messages' => Http::response(['error' => ['message' => 'overloaded']], 529)]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/story/run', ['prompt' => 'A fox learns to read', 'skill' => 'plain', 'model_key' => 'claude-sonnet-5-5'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed');

    expect($this->user->refresh()->balance_micros)->toBe(5_000_000);
});

it('answers 402 with the cost and the balance when a run is not affordable', function () {
    priceRow('fal', 'fal-ai/gpt-image-1.5', 'image', 34_000);
    config(['virgoai.credentials.FAL_KEY' => 'fal-test-key']);
    $provider = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'fal-ai/gpt-image-1.5', 'name' => 'GPT Image', 'enabled' => true, 'priority' => 100]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'image']);

    Http::fake();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/image/run', ['prompt' => 'A brass compass', 'model_key' => 'fal-ai/gpt-image-1.5'])
        ->assertStatus(402)
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'Add credit'));

    Http::assertNothingSent();
    expect(Generation::count())->toBe(0);
});

it('refunds a fal run the provider rejects at submit', function () {
    priceRow('fal', 'fal-ai/gpt-image-1.5', 'image', 34_000);
    config(['virgoai.credentials.FAL_KEY' => 'fal-test-key']);
    $this->user->forceFill(['balance_micros' => 1_000_000])->save();
    $provider = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    $model = AiModel::create(['ai_provider_id' => $provider->id, 'key' => 'fal-ai/gpt-image-1.5', 'name' => 'GPT Image', 'enabled' => true, 'priority' => 100]);
    ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => 'image']);

    Http::fake(['queue.fal.run/*' => Http::response(['detail' => 'Unauthorized'], 401)]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/tools/image/run', ['prompt' => 'A brass compass', 'model_key' => 'fal-ai/gpt-image-1.5'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed');

    expect($this->user->refresh()->balance_micros)->toBe(1_000_000);
});
