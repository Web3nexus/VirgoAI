<?php

namespace App\Billing\Payments;

/**
 * Checks the signatures providers put on their webhooks. Both checks compare in constant time.
 */
class WebhookSignature
{
    /** Paddle: Paddle-Signature is "ts=<unix>;h1=<hex>". The HMAC covers "<ts>:<raw body>". */
    public static function paddleValid(string $header, string $rawBody, string $secret, int $toleranceSeconds, ?int $now = null): bool
    {
        if ($header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(';', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 'ts') {
                $timestamp = $value;
            } elseif ($key === 'h1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        $now ??= time();
        if (abs($now - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.':'.$rawBody, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** Flutterwave sends the secret hash itself in the verif-hash header. */
    public static function flutterwaveValid(string $header, string $secretHash): bool
    {
        return $header !== '' && $secretHash !== '' && hash_equals($secretHash, $header);
    }
}
