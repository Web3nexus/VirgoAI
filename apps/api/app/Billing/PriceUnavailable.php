<?php

namespace App\Billing;

use RuntimeException;

/** The model has no usable price, so it cannot run for a user. The message says why, in plain words. */
class PriceUnavailable extends RuntimeException {}
