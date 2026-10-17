<?php

namespace App\Console\Commands;

use App\Billing\SubscriptionCycle;
use Illuminate\Console\Command;

class RunSubscriptionCycle extends Command
{
    protected $signature = 'billing:renew';

    protected $description = 'Grant monthly plan credit, expire unused credit, and end lapsed plans';

    public function handle(SubscriptionCycle $cycle): int
    {
        $counts = $cycle->run();

        $this->info("Credit granted: {$counts['granted']} · past due: {$counts['past_due']} · ended: {$counts['ended']}");

        return self::SUCCESS;
    }
}
