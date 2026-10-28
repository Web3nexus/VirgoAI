<?php

use App\Ai\Fal\FalEndpoints;
use App\Billing\PricingSettings;
use App\Billing\RateCard;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AppSetting;
use App\Models\ModelPrice;
use App\Models\ProviderCapability;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['balance_micros' => 50_000_000]);
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

    $anthropic = AiProvider::create(['key' => 'anthropic', 'name' => 'Anthropic', 'status' => 'active', 'priority' => 50]);
    $sonnet = AiModel::create(['ai_provider_id' => $anthropic->id, 'key' => 'claude-sonnet-5-5', 'name' => 'Sonnet', 'enabled' => true, 'priority' => 50]);
    ProviderCapability::create(['ai_model_id' => $sonnet->id, 'capability' => 'text']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'input_mtok', 'cost_micros' => 2_000_000, 'source' => 'anthropic_docs']);
    ModelPrice::create(['provider_key' => 'anthropic', 'model_key' => 'claude-sonnet-5-5', 'unit' => 'output_mtok', 'cost_micros' => 10_000_000, 'source' => 'anthropic_docs']);

    $fal = AiProvider::create(['key' => 'fal', 'name' => 'fal.ai', 'status' => 'active', 'priority' => 100]);
    foreach ([[FalEndpoints::IMAGE_TEXT, 'image'], [FalEndpoints::VIDEO_SEEDANCE_REF, 'video']] as [$key, $cap]) {
        $model = AiModel::create(['ai_provider_id' => $fal->id, 'key' => $key, 'name' => $key, 'enabled' => true, 'priority' => 100]);
        ProviderCapability::create(['ai_model_id' => $model->id, 'capability' => $cap]);
    }
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => FalEndpoints::IMAGE_TEXT, 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => FalEndpoints::VIDEO_SEEDANCE_REF, 'unit' => 'second', 'cost_micros' => 303_400, 'source' => 'fal_api']);
});

it('sells every priced unit above its cost plus the margin, so the card loses no money', function () {
    expect(app(RateCard::class)->verify())->toBe([]);

    foreach (ModelPrice::all() as $price) {
        $sell = app(PricingSettings::class)->sell((int) $price->cost_micros);
        expect($sell)->toBeGreaterThanOrEqual((int) ceil($price->cost_micros * 1.4));
    }
});

it('prices a one-minute film as the sum of its parts, each at sell price', function () {
    $card = app(RateCard::class)->owner();
    $minute = collect($card['director'])->firstWhere(fn ($row) => $row['length_seconds'] === 60 && $row['quality'] === '720p');

    // Four takes of 15 seconds at 303,400 micro-dollars a second, each sold at 1.4 times cost.
    $takeSell = (int) ceil(15 * 303_400 * 1.4);

    expect($minute['takes'])->toBe(4)
        ->and($minute['parts']['takes']['price_micros'])->toBe(4 * $takeSell)
        ->and($minute['total_micros'])->toBe($minute['parts']['director']['price_micros'] + $minute['parts']['board']['price_micros'] + $minute['parts']['takes']['price_micros'])
        ->and($minute['total_cost_micros'])->toBeLessThan($minute['total_micros'])
        ->and($minute['part_credits']['takes'])->toBe(round(4 * $takeSell / 1000, 1));
});

it('shows users credits and sell prices, and never the cost or the margin', function () {
    $json = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/rate-card')->assertOk()->json();

    expect($json['credit']['usd'])->toBe(0.001)
        ->and($json['models'])->not->toBeEmpty()
        ->and($json['models'][0])->toHaveKeys(['label', 'unit', 'credits_per_unit', 'price_usd'])
        ->and(json_encode($json))->not->toContain('cost_usd')
        ->and(json_encode($json))->not->toContain('kept');
});

it('gives the owner the cost and what is kept, and gives everyone else nothing', function () {
    $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/admin/rate-card')->assertNotFound();

    $owner = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/admin/rate-card')->assertOk()->json();

    expect($owner['problems'])->toBe([])
        ->and($owner['card']['models'][0])->toHaveKeys(['cost_usd', 'kept_usd']);
});

it('refuses a margin below the floor, and never uses one even if it was stored', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/pricing/margin', ['margin_percent' => 10])
        ->assertUnprocessable();

    AppSetting::write(PricingSettings::MARGIN_KEY, 0);

    expect(app(PricingSettings::class)->marginPercent())->toBe(40);
});

it('passes the CI check when every price covers its cost', function () {
    $this->artisan('billing:rate-card', ['--check' => true])->assertSuccessful();
});
