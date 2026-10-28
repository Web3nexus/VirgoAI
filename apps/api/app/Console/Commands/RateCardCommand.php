<?php

namespace App\Console\Commands;

use App\Billing\RateCard;
use Illuminate\Console\Command;

/**
 * Prints the rate card as a Markdown table and checks every price against its cost plus the margin. Exits with an
 * error when a price would lose money, so it can run in continuous integration.
 */
class RateCardCommand extends Command
{
    protected $signature = 'billing:rate-card {--check : Only check the prices, without printing the card}';

    protected $description = 'Print the credit rate card and check that no price sells below cost plus margin';

    public function handle(RateCard $rateCard): int
    {
        $problems = $rateCard->verify();

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info('Every price covers its cost plus the margin.');

            return self::SUCCESS;
        }

        $card = $rateCard->owner();
        $this->line('# virgoAi rate card');
        $this->line('');
        $this->line($card['credit']['note']);
        $this->line('');
        $this->line('## Models');
        $this->line('| Model | Unit | Credits per unit | Sell price (USD) | Cost (USD) | Kept (USD) |');
        $this->line('|---|---|---:|---:|---:|---:|');

        foreach ($card['models'] as $row) {
            $this->line(sprintf('| %s | %s | %s | %s | %s | %s |', $row['label'], $row['unit'], number_format($row['credits_per_unit'], 1), number_format($row['price_usd'], 4), number_format($row['cost_usd'], 4), number_format($row['kept_usd'], 4)));
        }

        $this->line('');
        $this->line('## Director packages (720p and 1080p, landscape 16:9)');
        $this->line('| Length | Quality | Shots | Chapters | Director credits | Board credits | Shot credits | Total credits | Total (USD) | Kept after fees (USD) |');
        $this->line('|---|---|---:|---:|---:|---:|---:|---:|---:|---:|');

        foreach ($card['director'] as $package) {
            if (! $package['available']) {
                $this->line(sprintf('| %d s | %s | not priced yet | | | | | | | |', $package['length_seconds'], $package['quality']));

                continue;
            }

            $this->line(sprintf(
                '| %d s | %s | %d | %d | %s | %s | %s | %s | %s | %s |',
                $package['length_seconds'],
                $package['quality'],
                $package['takes'],
                $package['chapters'],
                number_format($package['part_credits']['director'], 1),
                number_format($package['part_credits']['board'], 1),
                number_format($package['part_credits']['takes'], 1),
                number_format($package['total_credits'], 1),
                number_format($package['total_micros'] / 1_000_000, 2),
                number_format($package['kept_after_fees_usd'], 2),
            ));
        }

        return self::SUCCESS;
    }
}
