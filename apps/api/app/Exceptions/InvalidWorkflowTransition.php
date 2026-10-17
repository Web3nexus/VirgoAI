<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidWorkflowTransition extends RuntimeException
{
    public static function between(string $subject, string $from, string $to): self
    {
        return new self("Illegal {$subject} transition: {$from} → {$to}.");
    }
}
