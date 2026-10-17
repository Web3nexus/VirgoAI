<?php

namespace App\Http\Controllers\Api;

use App\Billing\Payments\PaymentEvents;
use App\Billing\Payments\WebhookSignature;
use App\Http\Controllers\Controller;
use App\Settings\ProviderCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Payment provider webhooks. An unsigned or wrongly signed request is refused before any work is done.
 * A transient failure (such as Flutterwave being unreachable during verification) answers 503 so the
 * provider retries; a permanent refusal answers 200 so it stops.
 */
class PaymentWebhookController extends Controller
{
    public function flutterwave(Request $request, PaymentEvents $events): JsonResponse
    {
        $secret = ProviderCredentials::get('FLUTTERWAVE_SECRET_HASH');

        if ($secret === null) {
            return response()->json(['message' => 'Flutterwave webhooks are not configured.'], 503);
        }

        if (! WebhookSignature::flutterwaveValid((string) $request->header('verif-hash', ''), $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = (array) $request->json()->all();

        try {
            $outcome = $events->flutterwave($payload, $request->getContent());
        } catch (Throwable $e) {
            Log::error('Flutterwave webhook could not be processed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Try again later.'], 503);
        }

        return response()->json(['outcome' => $outcome]);
    }

    public function paddle(Request $request, PaymentEvents $events): JsonResponse
    {
        $secret = ProviderCredentials::get('PADDLE_WEBHOOK_SECRET');

        if ($secret === null) {
            return response()->json(['message' => 'Paddle webhooks are not configured.'], 503);
        }

        $valid = WebhookSignature::paddleValid(
            (string) $request->header('Paddle-Signature', ''),
            $request->getContent(),
            $secret,
            (int) config('virgoai.billing.paddle.tolerance_seconds', 300),
        );

        if (! $valid) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        try {
            $outcome = $events->paddle((array) $request->json()->all());
        } catch (Throwable $e) {
            Log::error('Paddle webhook could not be processed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Try again later.'], 500);
        }

        return response()->json(['outcome' => $outcome]);
    }
}
