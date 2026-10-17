<?php

use App\Billing\Checkout;
use App\Billing\Fulfilment;
use App\Billing\Quote;
use App\Billing\SubscriptionCycle;
use App\Billing\Subscriptions;
use App\Billing\Wallet;
use App\Models\CreditPack;
use App\Models\LedgerEntry;
use App\Models\ModelPrice;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Settings\SystemSettings;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
});

function proPlan(): Plan
{
    return Plan::query()->where('key', 'pro')->firstOrFail();
}

it('seeds three monthly and yearly plans, and a yearly plan is cheaper per month', function () {
    $plans = Plan::query()->orderBy('sort')->get();

    expect($plans->pluck('key')->all())->toBe(['starter', 'pro', 'studio']);
    foreach ($plans as $plan) {
        expect($plan->yearly_micros / 12)->toBeLessThan($plan->monthly_micros);
    }
});

it('grants the monthly credit as subscription credit, separate from top-up credit', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);

    $this->user->refresh();

    expect($this->user->subscription_credit_micros)->toBe(49_000_000)
        ->and($this->user->balance_micros)->toBe(49_000_000);
});

it('spends subscription credit before purchased credit, and keeps the purchased part', function () {
    $wallet = app(Wallet::class);
    $wallet->grantPurchasedCredit($this->user, 10_000_000, 'Top-up');
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);

    $wallet->reserveRun($this->user->refresh(), new Quote(1, 20_000_000, 'image', 1, false), [
        'user_id' => $this->user->id, 'tool' => 'image', 'capability' => 'image', 'provider_key' => 'fal',
        'model_key' => 'x', 'prompt' => 'a fox', 'options' => [], 'output_type' => 'image',
    ]);

    $this->user->refresh();

    // 20 of the 49 subscription credit is spent. The top-up is untouched.
    expect($this->user->subscription_credit_micros)->toBe(29_000_000)
        ->and($this->user->balance_micros)->toBe(39_000_000);
});

it('expires unused subscription credit at the end of the cycle and grants the next month', function () {
    $subscriptions = app(Subscriptions::class);
    $subscriptions->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);
    app(Wallet::class)->grantPurchasedCredit($this->user, 5_000_000, 'Top-up');

    Subscription::query()->update(['next_credit_at' => now()->subMinute()]);
    $counts = app(SubscriptionCycle::class)->run();

    $this->user->refresh();

    expect($counts['granted'])->toBe(1)
        ->and($this->user->subscription_credit_micros)->toBe(49_000_000)
        ->and($this->user->balance_micros)->toBe(54_000_000)
        // The unused 49 from the last cycle is expired and written to the ledger, not silently dropped.
        ->and(LedgerEntry::where('type', LedgerEntry::TYPE_EXPIRY)->value('amount_micros'))->toBe(-49_000_000);
});

it('marks a plan past due at the end of its period with no renewal, and grants nothing', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);
    Subscription::query()->update(['current_period_end' => now()->subMinute(), 'next_credit_at' => now()->subMinute()]);

    $counts = app(SubscriptionCycle::class)->run();

    $sub = Subscription::first();
    $this->user->refresh();

    expect($counts['past_due'])->toBe(1)
        ->and($sub->status)->toBe(Subscription::STATUS_PAST_DUE)
        ->and($sub->isEntitled())->toBeFalse()
        ->and($this->user->subscription_credit_micros)->toBe(0)
        ->and($this->user->balance_micros)->toBe(0);
});

it('ends a past-due plan after the grace window', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);
    Subscription::query()->update([
        'status' => Subscription::STATUS_PAST_DUE,
        'current_period_end' => now()->subDays(Subscription::GRACE_DAYS + 1),
    ]);

    app(SubscriptionCycle::class)->run();

    expect(Subscription::first()->status)->toBe(Subscription::STATUS_ENDED)
        ->and(Subscription::first()->ended_at)->not->toBeNull();
});

it('ends a cancelled plan at the end of its period', function () {
    $sub = app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);
    app(Subscriptions::class)->cancel($sub);
    Subscription::query()->update(['current_period_end' => now()->subMinute()]);

    app(SubscriptionCycle::class)->run();

    expect(Subscription::first()->status)->toBe(Subscription::STATUS_ENDED);
});

it('restores a past-due plan at once when its renewal is paid', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);
    Subscription::query()->update(['status' => Subscription::STATUS_PAST_DUE, 'current_period_end' => now()->subDay()]);

    $purchase = Purchase::create([
        'user_id' => $this->user->id, 'kind' => Purchase::KIND_RENEWAL, 'item_key' => 'pro', 'interval' => 'monthly',
        'amount_micros' => 49_000_000, 'credits_micros' => 49_000_000, 'status' => 'pending', 'provider' => 'manual',
    ]);
    app(Fulfilment::class)->markPaid($purchase);

    $sub = Subscription::first();
    expect($sub->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and($sub->isEntitled())->toBeTrue();
});

it('fulfils a purchase once, however many times it is marked paid', function () {
    $pack = CreditPack::query()->where('key', 'topup_25')->firstOrFail();
    $purchase = app(Checkout::class)->pack($this->user, $pack->key, 'paddle');

    $fulfilment = app(Fulfilment::class);
    $fulfilment->markPaid($purchase);
    $fulfilment->markPaid($purchase);

    expect($this->user->refresh()->balance_micros)->toBe(25_000_000)
        ->and(LedgerEntry::where('type', LedgerEntry::TYPE_PURCHASE)->count())->toBe(1);
});

it('gives a subscriber the discounted top-up price, and a non-subscriber the list price', function () {
    $pack = CreditPack::query()->where('key', 'topup_100')->firstOrFail();

    expect(Checkout::packPriceFor($this->user, $pack))->toBe(100_000_000);

    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);

    // 15% off for a subscriber.
    expect(Checkout::packPriceFor($this->user->refresh(), $pack))->toBe(85_000_000);
});

it('prices a yearly plan as twelve months of credit, paid up front', function () {
    $purchase = app(Checkout::class)->plan($this->user, 'pro', Subscription::INTERVAL_YEARLY, 'paddle');

    expect($purchase->amount_micros)->toBe(470_000_000)
        ->and($purchase->credits_micros)->toBe(49_000_000 * 12)
        ->and($purchase->status)->toBe('pending');
});

it('grants nothing for a pending purchase', function () {
    app(Checkout::class)->plan($this->user, 'pro', Subscription::INTERVAL_MONTHLY, 'paddle');

    expect($this->user->refresh()->balance_micros)->toBe(0)
        ->and(Subscription::count())->toBe(0);
});

it('shows the public catalog with what each plan buys, and no margin', function () {
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'fal-ai/gpt-image-1.5', 'unit' => 'image', 'cost_micros' => 34_000, 'source' => 'fal_api']);
    ModelPrice::create(['provider_key' => 'fal', 'model_key' => 'bytedance/seedance-2.0/image-to-video', 'unit' => 'second', 'cost_micros' => 302_400, 'source' => 'fal_api']);

    $response = $this->getJson('/api/v1/pricing')
        ->assertOk()
        ->assertJsonPath('plans.0.key', 'starter')
        ->assertJsonPath('plans.1.highlighted', true)
        ->assertJsonCount(2, 'packs');

    expect($response->getContent())->not->toContain('margin')
        ->and($response->json('plans.1.monthly_tokens'))->toBe(49000)
        ->and($response->json('plans.1.yearly_savings_percent'))->toBeGreaterThan(0);
});

it('shows the owner what each plan keeps', function () {
    $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/admin/billing')
        ->assertOk()
        ->assertJsonPath('economics.1.key', 'pro')
        ->assertJsonPath('economics.1.monthly.revenue_usd', 49);
});

it('creates a plan and refuses a yearly price above twelve months', function () {
    $body = [
        'key' => 'team', 'name' => 'Team', 'monthly_usd' => 80, 'yearly_usd' => 800, 'monthly_credit_usd' => 80,
        'features' => ['Shared workspace'], 'highlighted' => false, 'active' => true, 'sort' => 4,
    ];

    $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/admin/billing/plans', $body)->assertCreated();
    expect(Plan::where('key', 'team')->value('monthly_micros'))->toBe(80_000_000);

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/billing/plans', [...$body, 'key' => 'bad', 'yearly_usd' => 2000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('yearly_usd');
});

it('will not delete a plan with live subscribers', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson('/api/v1/admin/billing/plans/'.proPlan()->id)
        ->assertUnprocessable();
});

it('lets the owner mark a bank transfer paid, which grants the credit', function () {
    $purchase = app(Checkout::class)->pack($this->user, 'topup_25', 'paddle');

    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/admin/billing/purchases/{$purchase->id}/paid")
        ->assertOk()
        ->assertJsonPath('status', 'paid');

    expect($this->user->refresh()->balance_micros)->toBe(25_000_000);
});

it('reports the signed-in user their wallet split and plan', function () {
    app(Subscriptions::class)->activate($this->user, proPlan(), Subscription::INTERVAL_MONTHLY);

    $this->actingAs($this->user->refresh(), 'sanctum')
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('subscription.plan_name', 'Pro')
        ->assertJsonPath('subscription.entitled', true)
        ->assertJsonPath('subscription_tokens', 49000);
});

it('promotes an existing user to admin, and removes it, but not for yourself', function () {
    $other = User::factory()->create();

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/team', ['email' => $other->email])
        ->assertCreated();
    expect($other->refresh()->isSuperAdmin())->toBeTrue();

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/admin/team/{$other->id}")
        ->assertOk();
    expect($other->refresh()->isSuperAdmin())->toBeFalse();

    $this->actingAs($this->owner, 'sanctum')
        ->deleteJson("/api/v1/admin/team/{$this->owner->id}")
        ->assertUnprocessable();
});

it('keeps the mail engine on the log driver until SMTP is set up in Settings', function () {
    expect(SystemSettings::mailDriver())->toBe('log');

    $this->actingAs($this->owner, 'sanctum')
        ->postJson('/api/v1/admin/system/test-email')
        ->assertOk()
        ->assertJsonPath('ok', true);
});
