<?php

use App\Billing\Quote;
use App\Billing\Wallet;
use App\Enums\Capability;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->user = User::factory()->create(['balance_micros' => 10_000_000]);
    $this->wallet = app(Wallet::class);
});

function reserve(User $user, Wallet $wallet, int $price): Generation
{
    return $wallet->reserveRun($user, new Quote((int) ($price / 1.4), $price, 'test', 1, true), [
        'user_id' => $user->id,
        'tool' => 'test',
        'capability' => Capability::Video->value,
        'provider_key' => 'fal',
        'model_key' => 'test',
        'prompt' => 'test',
        'options' => [],
        'output_type' => 'video',
    ]);
}

it('gives back the part of a reservation that was not used', function () {
    $run = reserve($this->user, $this->wallet, 5_000_000);

    $this->wallet->settle($run, new Quote(1_000_000, 2_000_000, 'test', 1, false));

    expect($run->refresh()->price_micros)->toBe(2_000_000)
        ->and($this->user->refresh()->balance_micros)->toBe(8_000_000);
});

it('never charges more than the reservation, and logs the overrun for the owner', function () {
    Log::spy();
    $run = reserve($this->user, $this->wallet, 5_000_000);

    $this->wallet->settle($run, new Quote(7_000_000, 9_000_000, 'test', 1, false));

    expect($run->refresh()->price_micros)->toBe(5_000_000)
        ->and($run->cost_micros)->toBe(7_000_000)
        ->and($this->user->refresh()->balance_micros)->toBe(5_000_000);

    Log::shouldHaveReceived('error')->once();
});

it('returns the whole reservation once, even when a refund is asked for twice', function () {
    $run = reserve($this->user, $this->wallet, 5_000_000);

    $this->wallet->refund($run);
    $this->wallet->refund($run);

    expect($this->user->refresh()->balance_micros)->toBe(10_000_000);
});
