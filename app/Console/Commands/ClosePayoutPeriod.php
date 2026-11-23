<?php

namespace App\Console\Commands;

use App\Services\PayoutService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Close a payout month (spec §2.4 step 1, C5): mark counted minutes,
 * split the pool, create payout rows for authors at/above the ৳500
 * minimum. Defaults to the last completed month.
 */
class ClosePayoutPeriod extends Command
{
    protected $signature = 'payouts:close {period? : Month to close (YYYY-MM), defaults to last month}';

    protected $description = 'Close a payout month: compute the pool and create payout rows';

    public function handle(PayoutService $payouts): int
    {
        $period = $this->argument('period') ?? now()->subMonth()->format('Y-m');

        try {
            $result = $payouts->closePeriod($period);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Closed {$result['period']}: gross ৳{$result['gross_bdt']}, fee ৳{$result['fee_bdt']}, net ৳{$result['net_bdt']}, pool ৳{$result['pool_bdt']}, distributed ৳{$result['distributed_bdt']}, remainder ৳{$result['remainder_bdt']}, payouts created: {$result['payouts_created']}.");

        return self::SUCCESS;
    }
}
