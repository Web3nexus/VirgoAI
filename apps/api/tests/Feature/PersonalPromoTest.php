<?php

use App\Billing\PersonalPromo;
use App\Models\AppSetting;
use App\Models\Purchase;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
});

function enablePromo(array $overrides = []): void
{
    AppSetting::write(PersonalPromo::SETTING_KEY, array_merge([
        'enabled' => true,
        'code' => 'WELCOME_PROMO',
        'percent' => 50,
        'window_hours' => 3,
    ], $overrides));
}

it('shows nothing while the offer is inactive', function () {
    $user = User::factory()->create();

    expect(app(PersonalPromo::class)->for($user))->toBeNull();

    enablePromo(['enabled' => false]);

    expect(app(PersonalPromo::class)->for($user))->toBeNull();
});

it('discounts every plan for a new account inside the window', function () {
    enablePromo();
    $user = User::factory()->create(['created_at' => now()->subMinutes(10)]);

    $offer = app(PersonalPromo::class)->for($user);

    expect($offer)->not->toBeNull()
        ->and($offer['code'])->toBe('WELCOME_PROMO')
        ->and($offer['percent'])->toBe(50)
        ->and($offer['plans']['starter']['monthly_usd'])->toBe(9.5)
        ->and($offer['plans']['pro']['monthly_usd'])->toBe(24.5)
        ->and($offer['plans']['pro']['yearly_usd'])->toBe(235.0);
});

it('ends the offer when the window closes, counted from sign-up', function () {
    enablePromo(['window_hours' => 3]);

    $late = User::factory()->create(['created_at' => now()->subHours(4)]);
    $inside = User::factory()->create(['created_at' => now()->subHours(2)]);

    expect(app(PersonalPromo::class)->for($late))->toBeNull()
        ->and(app(PersonalPromo::class)->for($inside))->not->toBeNull();
});

it('never shows the offer to an account that has bought a plan', function () {
    enablePromo();
    $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);

    Purchase::create([
        'user_id' => $user->id,
        'kind' => Purchase::KIND_SUBSCRIPTION,
        'item_key' => 'starter',
        'interval' => 'monthly',
        'amount_micros' => 19_000_000,
        'credits_micros' => 19_000_000,
        'status' => Purchase::STATUS_PAID,
        'provider' => 'manual',
        'paid_at' => now(),
    ]);

    expect(app(PersonalPromo::class)->for($user))->toBeNull();
});

it('lets the admin switch the offer on and off and validates the terms', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/pricing/promo', ['enabled' => true, 'code' => 'WELCOME_PROMO', 'percent' => 50, 'window_hours' => 3])
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('percent', 50);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/pricing/promo', ['enabled' => false, 'code' => 'WELCOME_PROMO', 'percent' => 50, 'window_hours' => 3])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    $this->actingAs($this->owner, 'sanctum')
        ->patchJson('/api/v1/admin/pricing/promo', ['enabled' => true, 'code' => 'WELCOME PROMO', 'percent' => 95, 'window_hours' => 3])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code', 'percent']);
});

it('returns the offer on the signed-in billing view', function () {
    enablePromo();
    $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('promo.percent', 50)
        ->assertJsonPath('promo.plans.pro.monthly_usd', 24.5);
});

it('records the discounted price on a new plan purchase', function () {
    enablePromo();
    $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);

    $purchase = app(\App\Billing\Checkout::class)->plan($user, 'starter', 'monthly', 'paddle');

    expect($purchase->amount_micros)->toBe(9_500_000);
});

it('refuses to open a checkout for an offer price the provider would not charge', function () {
    enablePromo();
    $user = User::factory()->create(['created_at' => now()->subMinutes(5)]);
    $purchase = app(\App\Billing\Checkout::class)->plan($user, 'starter', 'monthly', 'paddle');

    expect(fn () => app(\App\Billing\Payments\PaymentCheckout::class)->start($purchase, $user))
        ->toThrow(\App\Billing\Payments\PaymentNotConfigured::class, 'cannot be charged yet');
});
