<?php

namespace App\Console\Commands;

use App\Services\BillingService;
use Illuminate\Console\Command;

/**
 * Phase 4 (§9.1): flip overdue subscriptions — trialing → expired,
 * active → past_due. Runs daily 00:05 Asia/Dhaka (routes/console.php).
 * Idempotent: a second run flips nothing and notifies nobody twice.
 */
class MarkPastDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:mark-past-due';

    protected $description = 'Flip overdue subscriptions: trialing to expired, active to past_due.';

    public function handle(BillingService $billing): int
    {
        $result = $billing->markPastDue();

        $this->info("Expired: {$result['expired']}; past_due: {$result['past_due']}.");

        return self::SUCCESS;
    }
}
