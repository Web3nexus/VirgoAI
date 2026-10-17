<?php

namespace App\Billing\Payments;

use App\Models\User;
use DomainException;

/**
 * Picks the payment provider from the customer's country: Flutterwave across Africa, Paddle everywhere else.
 * The customer does not choose it.
 */
class PaymentRouter
{
    public static function providerFor(User $user): string
    {
        $country = strtoupper((string) $user->country_code);

        if ($country === '') {
            throw new DomainException('Choose your country before you pay. It decides how payment is taken.');
        }

        return in_array($country, (array) config('pricing.flutterwave_countries', []), true) ? 'flutterwave' : 'paddle';
    }
}
