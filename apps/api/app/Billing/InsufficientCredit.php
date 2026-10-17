<?php

namespace App\Billing;

use RuntimeException;

class InsufficientCredit extends RuntimeException
{
    public function __construct(public readonly int $requiredMicros, public readonly int $balanceMicros)
    {
        parent::__construct(sprintf(
            'This run costs %s and your balance is %s. Add credit to continue.',
            Money::format($requiredMicros, 4),
            Money::format($balanceMicros, 4),
        ));
    }
}
