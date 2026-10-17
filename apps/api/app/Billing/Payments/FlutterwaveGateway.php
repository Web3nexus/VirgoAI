<?php

namespace App\Billing\Payments;

use App\Billing\Money;
use App\Models\Purchase;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flutterwave v3 hosted checkout and transaction verification.
 *
 * The purchase id is sent as tx_ref, which Flutterwave requires to be unique per payment. A plan purchase
 * passes the Flutterwave payment plan id, so the first charge starts the subscription and later charges
 * arrive as webhooks.
 */
class FlutterwaveGateway
{
    protected string $base;

    public function __construct()
    {
        $this->base = (string) config('virgoai.billing.flutterwave.base');
    }

    public function isConfigured(): bool
    {
        return ProviderCredentials::has('FLUTTERWAVE_SECRET_KEY');
    }

    /**
     * @return array{0: string, 1: string} The hosted checkout link and the provider reference (the tx_ref).
     */
    public function createCheckout(Purchase $purchase, User $user, ?int $paymentPlanId): array
    {
        $body = [
            'tx_ref' => $purchase->id,
            'amount' => number_format(Money::toUsd($purchase->amount_micros), 2, '.', ''),
            'currency' => 'USD',
            'redirect_url' => rtrim((string) config('virgoai.billing.frontend_url'), '/').'/billing?purchase='.$purchase->id,
            'customer' => ['email' => $user->email, 'name' => $user->name],
            'customizations' => ['title' => 'virgoAi', 'description' => $this->describe($purchase)],
            'meta' => ['purchase_id' => $purchase->id],
        ];

        if ($paymentPlanId !== null) {
            $body['payment_plan'] = $paymentPlanId;
        }

        $response = $this->client()->post($this->base.'/payments', $body);

        $link = $response->json('data.link');

        if (! $response->successful() || ! is_string($link) || $link === '') {
            throw new RuntimeException('Flutterwave did not return a checkout link: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return [$link, $purchase->id];
    }

    /**
     * Ask Flutterwave for the transaction itself. Never trust a webhook body alone.
     *
     * @return array<string, mixed>
     */
    public function verifyTransaction(string|int $transactionId): array
    {
        $response = $this->client()->get($this->base."/transactions/{$transactionId}/verify");

        if (! $response->successful() || $response->json('status') !== 'success') {
            throw new RuntimeException('Flutterwave could not verify the transaction: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json('data');
    }

    /**
     * Create a recurring plan. Returns the plan id to store on the plan.
     */
    public function createPaymentPlan(string $name, int $amountMicros, string $interval, int $duration): int
    {
        $response = $this->client()->post($this->base.'/payment-plans', [
            'name' => $name,
            'amount' => number_format(Money::toUsd($amountMicros), 2, '.', ''),
            'currency' => 'USD',
            'interval' => $interval,
            'duration' => $duration,
        ]);

        $id = $response->json('data.id');

        if (! $response->successful() || ! is_numeric($id)) {
            throw new RuntimeException('Flutterwave did not create the payment plan: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (int) $id;
    }

    private function describe(Purchase $purchase): string
    {
        return match ($purchase->kind) {
            Purchase::KIND_PACK => 'Top-up credit',
            default => 'Monthly plan',
        };
    }

    private function client()
    {
        $key = ProviderCredentials::get('FLUTTERWAVE_SECRET_KEY');

        if ($key === null) {
            throw new PaymentNotConfigured('Flutterwave is not set up. Add the secret key under Settings.');
        }

        return Http::withToken($key)->acceptJson()->asJson()->timeout(30);
    }
}
