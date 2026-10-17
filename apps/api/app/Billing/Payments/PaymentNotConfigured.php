<?php

namespace App\Billing\Payments;

use RuntimeException;

/** A payment provider is missing a key, or a plan or price has not been set up with it yet. */
class PaymentNotConfigured extends RuntimeException {}
