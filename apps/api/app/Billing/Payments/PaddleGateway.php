<?php

namespace App\Billing\Payments;

use App\Models\Purchase;
use App\Models\User;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paddle Billing: a transaction per purchase, opened as a checkout. Paddle creates the subscription and
 * its renewals itself; those arrive as signed webhooks.
 */
class PaddleGateway
{
    protected string $base;

    public function __construct()
    {
        $this->base = (string) config('virgoai.billing.paddle.base');
    }

    public function isConfigured(): bool
    {
        return ProviderCredentials::has('PADDLE_API_KEY');
    }

    /**
     * @param  string  $priceId  The Paddle price for this purchase (plan price, or a top-up price).
     * @return array{0: string, 1: string} The checkout URL and the Paddle transaction id.
     */
    public function createCheckout(Purchase $purchase, User $user, string $priceId): array
    {
        $response = $this->client()->post($this->base.'/transactions', [
            'items' => [['price_id' => $priceId, 'quantity' => 1]],
            'collection_mode' => 'automatic',
            'enable_checkout' => true,
            'custom_data' => ['purchase_id' => $purchase->id],
        ]);

        $transactionId = $response->json('data.id');
        $url = $response->json('data.checkout.url');

        if (! $response->successful() || ! is_string($transactionId) || ! is_string($url)) {
            throw new RuntimeException('Paddle did not open a checkout: '.($response->json('error.detail') ?? 'HTTP '.$response->status()));
        }

        return [$url, $transactionId];
    }

    /** Stop future renewals. The customer keeps access until the period they paid for ends. */
    public function cancelSubscription(string $subscriptionId): void
    {
        $response = $this->client()->post($this->base."/subscriptions/{$subscriptionId}/cancel", [
            'effective_from' => 'next_billing_period',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Paddle did not cancel the subscription: '.($response->json('error.detail') ?? 'HTTP '.$response->status()));
        }
    }

    private function client()
    {
        $key = ProviderCredentials::get('PADDLE_API_KEY');

        if ($key === null) {
            throw new PaymentNotConfigured('Paddle is not set up. Add the API key under Settings.');
        }

        return Http::withToken($key)->acceptJson()->asJson()->timeout(30);
    }
}
