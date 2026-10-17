<?php

use App\Billing\Checkout;
use App\Billing\Payments\PaymentCheckout;
use App\Billing\Payments\WebhookSignature;
use App\Billing\Subscriptions;
use App\Models\CreditPack;
use App\Models\LedgerEntry;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

const FW_HASH = 'fw-hash-test-value-0001';
const PADDLE_SECRET = 'pdl_ntfset_test_secret_0002';

beforeEach(function () {
    $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->user = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Buyer', 'country_code' => 'NG']);

    ProviderCredentials::set('FLUTTERWAVE_SECRET_KEY', 'FLWSECK_TEST-key-0003', $this->owner);
    ProviderCredentials::set('FLUTTERWAVE_SECRET_HASH', FW_HASH, $this->owner);
    ProviderCredentials::set('PADDLE_API_KEY', 'pdl_apikey_test_0004', $this->owner);
    ProviderCredentials::set('PADDLE_WEBHOOK_SECRET', PADDLE_SECRET, $this->owner);

    $pro = Plan::query()->where('key', 'pro')->firstOrFail();
    $pro->update([
        'flutterwave_monthly_plan_id' => 3807,
        'flutterwave_yearly_plan_id' => 3808,
        'paddle_monthly_price_id' => 'pri_01monthlypro',
        'paddle_yearly_price_id' => 'pri_01yearlypro',
    ]);
});

/** A Paddle-signed webhook: header and raw body as Paddle would send them. */
function paddleCall(array $envelope, ?int $timestamp = null, ?string $secret = null): array
{
    $body = json_encode($envelope);
    $ts = (string) ($timestamp ?? time());
    $h1 = hash_hmac('sha256', $ts.':'.$body, $secret ?? PADDLE_SECRET);

    return ['body' => $body, 'header' => "ts={$ts};h1={$h1}"];
}

function postPaddle(array $envelope, array $opts = []): TestResponse
{
    $call = paddleCall($envelope, $opts['ts'] ?? null, $opts['secret'] ?? null);

    return test()->call('POST', '/api/v1/webhooks/paddle', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PADDLE_SIGNATURE' => $opts['header'] ?? $call['header'],
    ], $call['body']);
}

function postFlutterwave(array $payload, ?string $hash = FW_HASH): TestResponse
{
    return test()->call('POST', '/api/v1/webhooks/flutterwave', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_VERIF_HASH' => (string) $hash,
    ], json_encode($payload));
}

function fwVerified(string $txId, string $txRef, string $amount = '19.00', string $status = 'successful', ?int $plan = null): array
{
    return ['status' => 'success', 'message' => 'ok', 'data' => [
        'id' => (int) $txId, 'tx_ref' => $txRef, 'status' => $status, 'amount' => (float) $amount, 'currency' => 'USD',
        'customer' => ['email' => 'buyer@example.com'], 'payment_plan' => $plan,
    ]];
}

/** A pending top-up purchase for the test user, already with a checkout link. */
function pendingPack(string $provider): Purchase
{
    return app(Checkout::class)->pack(test()->user, 'topup_25', $provider);
}

// Checkout: create once

it('opens a Flutterwave checkout once, and returns the same link on a repeat request', function () {
    Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/abc']])]);

    $purchase = pendingPack('flutterwave');
    $payments = app(PaymentCheckout::class);

    expect($payments->start($purchase, $this->user))->toBe('https://checkout.flutterwave.com/pay/abc')
        ->and($payments->start($purchase->refresh(), $this->user))->toBe('https://checkout.flutterwave.com/pay/abc');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['tx_ref'] === $purchase->id && $request['amount'] === '25.00' && $request['currency'] === 'USD');
});

it('opens a Paddle checkout once, linked to the purchase', function () {
    CreditPack::query()->where('key', 'topup_25')->update(['paddle_price_id' => 'pri_01topup']);
    Http::fake(['api.paddle.com/transactions' => Http::response(['data' => ['id' => 'txn_01abc', 'checkout' => ['url' => 'https://pay.paddle.io/txn_01abc']]], 201)]);

    $purchase = pendingPack('paddle');
    $payments = app(PaymentCheckout::class);

    expect($payments->start($purchase, $this->user))->toBe('https://pay.paddle.io/txn_01abc')
        ->and($payments->start($purchase->refresh(), $this->user))->toBe('https://pay.paddle.io/txn_01abc')
        ->and($purchase->refresh()->provider_ref)->toBe('txn_01abc');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['custom_data']['purchase_id'] === $purchase->id
        && $request['items'][0]['price_id'] === 'pri_01topup'
        && $request['enable_checkout'] === true);
});

it('routes a Nigerian customer to Flutterwave and a foreign customer to Paddle', function () {
    expect(\App\Billing\Payments\PaymentRouter::providerFor($this->user))->toBe('flutterwave')
        ->and(\App\Billing\Payments\PaymentRouter::providerFor(User::factory()->create(['country_code' => 'US'])))->toBe('paddle');
});

it('refuses to check out until the customer has a country', function () {
    $this->user->forceFill(['country_code' => null])->save();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/billing/plans', ['plan_key' => 'pro', 'interval' => 'monthly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('country_code');
});

it('refuses to open a checkout for a plan that is not set up with the provider', function () {
    Plan::query()->where('key', 'pro')->update(['flutterwave_monthly_plan_id' => null]);
    Http::fake();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/billing/plans', ['plan_key' => 'pro', 'interval' => 'monthly'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'not set up on Flutterwave'));

    Http::assertNothingSent();
});

it('returns a checkout link from the plan endpoint', function () {
    Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['data' => ['link' => 'https://checkout.flutterwave.com/pay/plan']])]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/billing/plans', ['plan_key' => 'pro', 'interval' => 'monthly'])
        ->assertCreated()
        ->assertJsonPath('checkout_url', 'https://checkout.flutterwave.com/pay/plan');

    Http::assertSent(fn ($request) => $request['payment_plan'] === 3807);
});

// Flutterwave webhooks

it('refuses a Flutterwave webhook with the wrong secret hash', function () {
    $purchase = pendingPack('flutterwave');
    $purchase->forceFill(['provider_ref' => $purchase->id])->save();

    postFlutterwave(['event' => 'charge.completed', 'data' => ['id' => 1]], 'wrong-hash')->assertUnauthorized();

    expect($this->user->refresh()->balance_micros)->toBe(0)
        ->and(PaymentEvent::count())->toBe(0);
});

it('grants a verified Flutterwave payment once, however often it is delivered', function () {
    $purchase = pendingPack('flutterwave');
    $purchase->forceFill(['provider_ref' => $purchase->id])->save();

    Http::fake(['api.flutterwave.com/v3/transactions/555/verify' => Http::response(fwVerified('555', $purchase->id, '25.00'))]);
    $payload = ['event' => 'charge.completed', 'data' => ['id' => 555, 'status' => 'successful']];

    postFlutterwave($payload)->assertOk()->assertJsonPath('outcome', 'fulfilled');
    postFlutterwave($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');
    postFlutterwave($payload)->assertOk()->assertJsonPath('outcome', 'duplicate');

    expect($this->user->refresh()->balance_micros)->toBe(25_000_000)
        ->and(LedgerEntry::where('type', LedgerEntry::TYPE_PURCHASE)->count())->toBe(1);
});

it('trusts the verified amount, not the webhook body', function () {
    $purchase = pendingPack('flutterwave');
    $purchase->forceFill(['provider_ref' => $purchase->id])->save();

    // The webhook claims success for the right charge, but the verified amount is half the price.
    Http::fake(['api.flutterwave.com/v3/transactions/556/verify' => Http::response(fwVerified('556', $purchase->id, '12.50'))]);

    postFlutterwave(['event' => 'charge.completed', 'data' => ['id' => 556, 'status' => 'successful', 'amount' => 25]])
        ->assertOk()
        ->assertJsonPath('outcome', 'amount_mismatch');

    expect($purchase->refresh()->status)->toBe(Purchase::STATUS_FAILED)
        ->and($this->user->refresh()->balance_micros)->toBe(0);
});

it('asks Flutterwave to retry when it cannot verify the charge', function () {
    Http::fake(['api.flutterwave.com/v3/transactions/557/verify' => Http::response(['status' => 'error'], 500)]);

    postFlutterwave(['event' => 'charge.completed', 'data' => ['id' => 557, 'status' => 'successful']])
        ->assertStatus(503);

    expect(PaymentEvent::count())->toBe(0);
});

it('creates and fulfils a renewal when Flutterwave charges an existing plan', function () {
    app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY);
    Subscription::query()->update(['current_period_end' => now()->subDay()]);

    Http::fake(['api.flutterwave.com/v3/transactions/600/verify' => Http::response(fwVerified('600', 'FLW-RENEW', '49.00', 'successful', 3807))]);

    postFlutterwave(['event' => 'charge.completed', 'data' => ['id' => 600, 'status' => 'successful']])
        ->assertOk()
        ->assertJsonPath('outcome', 'fulfilled');

    postFlutterwave(['event' => 'charge.completed', 'data' => ['id' => 600, 'status' => 'successful']])
        ->assertOk()
        ->assertJsonPath('outcome', 'duplicate');

    expect(Purchase::where('provider_ref', 'flw:600')->count())->toBe(1)
        ->and(Subscription::first()->status)->toBe(Subscription::STATUS_ACTIVE)
        ->and(Subscription::first()->isEntitled())->toBeTrue();
});

it('marks a Flutterwave plan cancelled, and access continues to the end of the paid period', function () {
    app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY, null);
    Subscription::query()->update(['provider' => 'flutterwave']);

    postFlutterwave(['event' => 'subscription.cancelled', 'data' => ['customer' => ['email' => 'buyer@example.com'], 'plan' => ['id' => 3807]]])
        ->assertOk()
        ->assertJsonPath('outcome', 'cancel_scheduled');

    $sub = Subscription::first();
    expect($sub->cancel_at_period_end)->toBeTrue()
        ->and($sub->isEntitled())->toBeTrue();
});

// Paddle webhooks

it('verifies the Paddle signature and rejects a forged or stale one', function () {
    $envelope = ['event_id' => 'evt_1', 'event_type' => 'transaction.completed', 'data' => ['id' => 'txn_x', 'status' => 'completed']];

    postPaddle($envelope, ['secret' => 'not-the-secret'])->assertUnauthorized();
    postPaddle($envelope, ['ts' => time() - 3600])->assertUnauthorized();

    expect(PaymentEvent::count())->toBe(0);
});

it('grants a Paddle purchase once, keyed by its event id', function () {
    $purchase = pendingPack('paddle');
    $purchase->forceFill(['provider_ref' => 'txn_01abc'])->save();

    $envelope = [
        'event_id' => 'evt_01paid',
        'event_type' => 'transaction.completed',
        'data' => [
            'id' => 'txn_01abc', 'status' => 'completed', 'currency_code' => 'USD',
            'custom_data' => ['purchase_id' => $purchase->id],
            'details' => ['totals' => ['subtotal' => '2500', 'total' => '2500']],
        ],
    ];

    postPaddle($envelope)->assertOk()->assertJsonPath('outcome', 'fulfilled');
    postPaddle($envelope)->assertOk()->assertJsonPath('outcome', 'duplicate');

    expect($this->user->refresh()->balance_micros)->toBe(25_000_000)
        ->and(LedgerEntry::where('type', LedgerEntry::TYPE_PURCHASE)->count())->toBe(1);
});

it('refuses a Paddle payment in the wrong currency and fails the purchase', function () {
    $purchase = pendingPack('paddle');
    $purchase->forceFill(['provider_ref' => 'txn_eur'])->save();

    postPaddle(['event_id' => 'evt_eur', 'event_type' => 'transaction.completed', 'data' => [
        'id' => 'txn_eur', 'status' => 'completed', 'currency_code' => 'EUR',
        'custom_data' => ['purchase_id' => $purchase->id], 'details' => ['totals' => ['subtotal' => '2500']],
    ]])->assertOk()->assertJsonPath('outcome', 'amount_mismatch');

    expect($purchase->refresh()->status)->toBe(Purchase::STATUS_FAILED)
        ->and($this->user->refresh()->balance_micros)->toBe(0);
});

it('creates a Paddle renewal from a recurring charge, once', function () {
    $sub = app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY);
    $sub->forceFill(['provider' => 'paddle', 'provider_ref' => 'sub_01live'])->save();

    $envelope = [
        'event_id' => 'evt_renew_1', 'event_type' => 'transaction.completed', 'data' => [
            'id' => 'txn_renew_1', 'status' => 'completed', 'currency_code' => 'USD', 'subscription_id' => 'sub_01live',
            'origin' => 'subscription_recurring',
            'items' => [['price' => ['id' => 'pri_01monthlypro']]],
            'details' => ['totals' => ['subtotal' => '4900']],
        ],
    ];

    postPaddle($envelope)->assertOk()->assertJsonPath('outcome', 'fulfilled');

    // The same charge arriving under a second event id is still one renewal.
    $envelope['event_id'] = 'evt_renew_1_retry';
    postPaddle($envelope)->assertOk()->assertJsonPath('outcome', 'duplicate');

    expect(Purchase::where('provider_ref', 'txn_renew_1')->count())->toBe(1)
        ->and(Subscription::first()->isEntitled())->toBeTrue();
});

it('marks a Paddle subscription ended when Paddle cancels it, and expires its credit', function () {
    $sub = app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY);
    $sub->forceFill(['provider' => 'paddle', 'provider_ref' => 'sub_01gone'])->save();

    postPaddle(['event_id' => 'evt_cancel', 'event_type' => 'subscription.canceled', 'data' => ['id' => 'sub_01gone']])
        ->assertOk()
        ->assertJsonPath('outcome', 'ended');

    expect(Subscription::first()->status)->toBe(Subscription::STATUS_ENDED)
        ->and($this->user->refresh()->subscription_credit_micros)->toBe(0);
});

it('answers 503 for Paddle webhooks until the signing secret is set', function () {
    ProviderCredentials::clear('PADDLE_WEBHOOK_SECRET', $this->owner);

    $this->postJson('/api/v1/webhooks/paddle', ['event_id' => 'x'])->assertStatus(503);
});

it('cancels a Paddle subscription at Paddle, then locally', function () {
    $sub = app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY);
    $sub->forceFill(['provider' => 'paddle', 'provider_ref' => 'sub_01cancel'])->save();

    Http::fake(['api.paddle.com/subscriptions/sub_01cancel/cancel' => Http::response(['data' => ['id' => 'sub_01cancel']])]);

    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/billing/cancel')->assertOk();

    Http::assertSent(fn ($request) => $request['effective_from'] === 'next_billing_period');
    expect(Subscription::first()->cancel_at_period_end)->toBeTrue();
});

it('leaves the plan unchanged when Paddle refuses the cancellation', function () {
    $sub = app(Subscriptions::class)->activate($this->user, Plan::query()->where('key', 'pro')->firstOrFail(), Subscription::INTERVAL_MONTHLY);
    $sub->forceFill(['provider' => 'paddle', 'provider_ref' => 'sub_01refused'])->save();

    Http::fake(['api.paddle.com/subscriptions/*' => Http::response(['error' => ['detail' => 'Not allowed']], 400)]);

    $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/billing/cancel')->assertStatus(502);

    expect(Subscription::first()->cancel_at_period_end)->toBeFalse();
});

// Signature rules

it('checks Paddle signatures against the body, the secret, and the clock', function () {
    $body = '{"event_id":"evt"}';
    $now = 1_700_000_000;
    $h1 = hash_hmac('sha256', $now.':'.$body, 'secret-x');

    expect(WebhookSignature::paddleValid("ts={$now};h1={$h1}", $body, 'secret-x', 300, $now))->toBeTrue()
        ->and(WebhookSignature::paddleValid("ts={$now};h1={$h1}", $body.' ', 'secret-x', 300, $now))->toBeFalse()
        ->and(WebhookSignature::paddleValid("ts={$now};h1={$h1}", $body, 'secret-x', 300, $now + 301))->toBeFalse()
        ->and(WebhookSignature::paddleValid('', $body, 'secret-x', 300, $now))->toBeFalse();
});
