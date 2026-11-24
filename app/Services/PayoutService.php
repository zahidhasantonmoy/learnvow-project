<?php

namespace App\Services;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Order;
use App\Models\PrintOrder;
use App\Models\ReadingMinute;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Notifications\AuthorNotification;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Author earnings + subscription pool + payout service (Phase 3 — spec §2).
 *
 * All money is int (৳); every amount is recomputed from the DB, never
 * trusted from client input (spec §0.2). Refund behaviour is LOCKED from
 * Phase 2: a refunded buyer KEEPS access — reverseSaleEarnings() never
 * touches user_books.
 *
 * Fee rounding (spec §2.6 worked example): the fee is rounded PER
 * subscription payment — round(৳99 × 2%) = ৳2 each, so 100 subscribers
 * pay ৳200 fee on ৳9,900 gross → ৳9,700 net → ৳7,760 pool. A single
 * round on the monthly total (৳198) would NOT reproduce the approved
 * example, so the per-payment rounding is the rule.
 */
class PayoutService
{
    /** Read a settings key. Null when the table/key is absent. */
    public function setting(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('settings')) {
            return $default;
        }

        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public function settingInt(string $key, int $default = 0): int
    {
        $value = $this->setting($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    /** Pool share pct (default 80); per-author share falls back to this. */
    public function sharePct(): int
    {
        return $this->settingInt('pool.share_pct', 80);
    }

    /**
     * Accrue the author's 80% on a paid order (spec §2.1).
     *
     * Called from BillingService::verifyPayment() only — never before the
     * order is paid. Net per item = what the buyer actually paid after
     * the coupon, INCLUDING the wallet portion (top-up money is real
     * money). Gift orders count; free (৳0) books accrue ৳0, i.e. no row.
     * Idempotent: a second call for the same order creates nothing.
     *
     * NOTE (known gap, spec §2.1 + task scope): orders whose total is
     * ৳0 because the wallet covered the full price are marked paid in
     * BillingService::createOrder() and never pass through verifyPayment(),
     * so they accrue nothing here. Fixing that needs a third call-site in
     * createOrder() — left for the coordinator/user to approve.
     */
    public function accrueSale(Order $order): void
    {
        $items = $order->items()->whereNotNull('book_id')->get();

        if ($items->isEmpty()) {
            return;
        }

        // Order-level net: cash total + wallet portion spent on the order.
        $net = (int) $order->total_bdt + (int) $order->wallet_used_bdt;

        if ($net <= 0) {
            return;
        }

        // Allocate the net across book items by their list value
        // (unit_price × quantity); a single-item order gets the full net.
        $grossItems = $items->sum(fn ($item) => (int) $item->unit_price_bdt * (int) $item->quantity);
        $period = $this->periodOf($order);

        DB::transaction(function () use ($order, $items, $net, $grossItems, $period) {
            foreach ($items as $item) {
                $book = $item->book;

                if ($book === null || $book->author_id === null) {
                    continue;
                }

                $already = AuthorEarning::where('order_id', $order->id)
                    ->where('book_id', $book->id)
                    ->where('type', AuthorEarning::TYPE_SALE)
                    ->exists();

                if ($already) {
                    continue;
                }

                $itemNet = $grossItems > 0
                    ? (int) round($net * ((int) $item->unit_price_bdt * (int) $item->quantity) / $grossItems)
                    : 0;

                if ($itemNet <= 0) {
                    continue;
                }

                $author = Author::find($book->author_id);
                $sharePct = $author !== null && $author->revenue_share_pct !== null
                    ? (int) $author->revenue_share_pct
                    : $this->sharePct();

                AuthorEarning::create([
                    'author_id' => $book->author_id,
                    'book_id' => $book->id,
                    'order_id' => $order->id,
                    'period' => $period,
                    'type' => AuthorEarning::TYPE_SALE,
                    'amount_bdt' => (int) round($itemNet * $sharePct / 100),
                    'share_pct' => $sharePct,
                    'note' => 'Sale — order '.$order->order_no,
                ]);
            }
        });
    }

    /**
     * Accrue the author's share on a DELIVERED print order (Phase 4
     * spec §2.3): first the partner cost, then 80% of what remains.
     * Worked example: price ৳454, partner cost ৳200 → (454 − 200) × 80%
     * = ৳203 (int, round). type=sale, NOT pool. Idempotent.
     *
     * Accrues nothing when: the order is not delivered, it is the
     * author's own copy (ordered_by_author), the partner cost is still
     * unknown (spec A4), or price − cost is ≤ 0.
     */
    public function accruePrintSale(PrintOrder $order): void
    {
        if ($order->status !== PrintOrder::STATUS_DELIVERED) {
            return;
        }

        if ($order->ordered_by_author) {
            return;
        }

        if ($order->partner_cost_bdt === null) {
            return;
        }

        $remaining = (int) $order->total_bdt - (int) $order->partner_cost_bdt;

        if ($remaining <= 0) {
            return;
        }

        $note = 'print order '.$order->order_no;

        if (AuthorEarning::where('note', $note)->where('type', AuthorEarning::TYPE_SALE)->exists()) {
            return;
        }

        $author = Author::find($order->author_id);
        $sharePct = $author !== null && $author->revenue_share_pct !== null
            ? (int) $author->revenue_share_pct
            : $this->sharePct();

        AuthorEarning::create([
            'author_id' => $order->author_id,
            'book_id' => $order->book_id,
            'order_id' => null,
            'period' => now()->format('Y-m'),
            'type' => AuthorEarning::TYPE_SALE,
            'amount_bdt' => (int) round($remaining * $sharePct / 100),
            'share_pct' => $sharePct,
            'note' => $note,
        ]);
    }

    /**
     * Reverse sale earnings on a Super Admin-approved refund (spec §2.1,
     * assumption A3): one negative `adjustment` row per original sale row.
     * The buyer's access is UNCHANGED — user_books is never touched.
     * Idempotent: a second call for the same order creates nothing.
     */
    public function reverseSaleEarnings(Order $order): void
    {
        $sales = AuthorEarning::where('order_id', $order->id)
            ->where('type', AuthorEarning::TYPE_SALE)
            ->get();

        if ($sales->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $sales) {
            foreach ($sales as $sale) {
                $already = AuthorEarning::where('order_id', $order->id)
                    ->where('book_id', $sale->book_id)
                    ->where('type', AuthorEarning::TYPE_ADJUSTMENT)
                    ->where('amount_bdt', '<', 0)
                    ->exists();

                if ($already) {
                    continue;
                }

                AuthorEarning::create([
                    'author_id' => $sale->author_id,
                    'book_id' => $sale->book_id,
                    'order_id' => $order->id,
                    'period' => $sale->period,
                    'type' => AuthorEarning::TYPE_ADJUSTMENT,
                    'amount_bdt' => -(int) $sale->amount_bdt,
                    'share_pct' => (int) $sale->share_pct,
                    'note' => 'Refund reversal — order '.$order->order_no,
                ]);
            }
        });
    }

    /**
     * Close a calendar month (spec §2.2–§2.5): mark counted minutes,
     * split the pool by counted reading time, carry the rounding
     * remainder to next month (A2), and create payout rows only for
     * authors whose unpaid total is >= pool.min_payout_bdt (৳500).
     *
     * @return array{period: string, gross_bdt: int, fee_bdt: int, net_bdt: int, pool_bdt: int, distributed_bdt: int, remainder_bdt: int, payouts_created: int}
     */
    public function closePeriod(string $period): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException("Period must be YYYY-MM, got [{$period}].");
        }

        return DB::transaction(function () use ($period) {
            $alreadyClosed = AuthorEarning::where('period', $period)
                    ->where('type', AuthorEarning::TYPE_POOL)->exists()
                || AuthorPayout::where('period', $period)->exists();

            if ($alreadyClosed) {
                throw new InvalidArgumentException("Period [{$period}] is already closed.");
            }

            $feeRate = $this->settingInt('pool.fee_rate_pct', 2);
            $sharePct = $this->sharePct();
            $minMinutes = $this->settingInt('pool.count_min_minutes', 10);
            $minPercent = $this->settingInt('pool.count_min_percent', 10);
            $minPayout = $this->settingInt('pool.min_payout_bdt', 500);

            // ---- Subscription revenue: verified premium payments in the month.
            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $end = (clone $start)->endOfMonth();

            $payments = DB::table('payments')
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->where('orders.type', 'premium')
                ->where('payments.status', 'verified')
                ->whereBetween('payments.verified_at', [$start, $end])
                ->select('orders.total_bdt', 'orders.wallet_used_bdt')
                ->get();

            $gross = 0;
            $fee = 0;
            foreach ($payments as $payment) {
                $net = (int) $payment->total_bdt + (int) $payment->wallet_used_bdt;
                $gross += $net;
                // Per-payment fee rounding (see class docblock §2.6).
                $fee += (int) round($net * $feeRate / 100);
            }
            $netTotal = $gross - $fee;

            // ---- Pool: floor(net × share) + remainder carried from last month.
            $carry = $this->settingInt('pool.carry_bdt', 0);
            $pool = (int) floor($netTotal * $sharePct / 100) + $carry;

            // ---- Mark counted minutes (spec §2.3): minutes >= 10 OR
            // reading_progress.percent >= 10; flagged and own-book rows
            // never count. Rows are marked, never deleted.
            $rows = ReadingMinute::where('period', $period)->get();
            $minutesByAuthor = [];

            foreach ($rows as $row) {
                $counted = false;

                if (! $row->flagged && ! $this->isOwnBookMinutes($row)) {
                    $progress = ReadingProgress::where('user_id', $row->user_id)
                        ->where('book_id', $row->book_id)
                        ->value('percent');
                    $counted = (int) $row->minutes >= $minMinutes
                        || (int) ($progress ?? 0) >= $minPercent;
                }

                if ($row->counted !== $counted) {
                    $row->update(['counted' => $counted]);
                }

                if ($counted) {
                    $minutesByAuthor[(int) $row->author_id] =
                        ($minutesByAuthor[(int) $row->author_id] ?? 0) + (int) $row->minutes;
                }
            }

            // ---- Split the pool by counted minutes (floor per author).
            $totalMinutes = array_sum($minutesByAuthor);
            $distributed = 0;

            if ($pool > 0 && $totalMinutes > 0) {
                foreach ($minutesByAuthor as $authorId => $minutes) {
                    $share = (int) floor($pool * $minutes / $totalMinutes);

                    if ($share <= 0) {
                        continue;
                    }

                    AuthorEarning::create([
                        'author_id' => $authorId,
                        'book_id' => null,
                        'order_id' => null,
                        'period' => $period,
                        'type' => AuthorEarning::TYPE_POOL,
                        'amount_bdt' => $share,
                        'share_pct' => $sharePct,
                        'note' => "Pool share — {$period} ({$minutes}/{$totalMinutes} counted minutes)",
                    ]);
                    $distributed += $share;
                }
            }

            // Remainder (A2) carries to next month's pool — never lost.
            $remainder = $pool - $distributed;
            $this->setSetting('pool.carry_bdt', (string) $remainder);

            // ---- Payout rows: unpaid accrued total (sales + pool) >= ৳500.
            // Unpaid = all earnings − amounts already committed in
            // pending/approved/paid payout rows (failed rows commit nothing:
            // the money was never sent, the same row is retried).
            $payoutsCreated = 0;

            $authorIds = AuthorEarning::distinct()->pluck('author_id');

            foreach ($authorIds as $authorId) {
                $author = Author::find($authorId);

                if ($author === null) {
                    continue;
                }

                $salesEarned = (int) AuthorEarning::where('author_id', $authorId)
                    ->whereIn('type', [AuthorEarning::TYPE_SALE, AuthorEarning::TYPE_ADJUSTMENT])
                    ->sum('amount_bdt');
                $poolEarned = (int) AuthorEarning::where('author_id', $authorId)
                    ->where('type', AuthorEarning::TYPE_POOL)
                    ->sum('amount_bdt');

                $committed = AuthorPayout::where('author_id', $authorId)
                    ->whereIn('status', [
                        AuthorPayout::STATUS_PENDING,
                        AuthorPayout::STATUS_APPROVED,
                        AuthorPayout::STATUS_PAID,
                    ])
                    ->selectRaw('COALESCE(SUM(sales_bdt), 0) as sales, COALESCE(SUM(pool_bdt), 0) as pool')
                    ->first();

                $salesUnpaid = $salesEarned - (int) ($committed->sales ?? 0);
                $poolUnpaid = $poolEarned - (int) ($committed->pool ?? 0);
                $totalUnpaid = $salesUnpaid + $poolUnpaid;

                if ($totalUnpaid < $minPayout) {
                    continue;
                }

                AuthorPayout::create([
                    'author_id' => $authorId,
                    'period' => $period,
                    'sales_bdt' => max(0, $salesUnpaid),
                    'pool_bdt' => max(0, $poolUnpaid),
                    'total_bdt' => $totalUnpaid,
                    'method' => $author->payout_method ?? 'bkash',
                    'destination' => $this->payoutDestination($author),
                    'status' => AuthorPayout::STATUS_PENDING,
                ]);
                $payoutsCreated++;
            }

            // Audit once per close, in the shared path (round 2 minor):
            // both the Filament action and the `payouts:close` CLI
            // command go through closePeriod(), so both now produce the
            // `payout.period_closed` row. Actor is NULL here — the
            // caller (Filament user vs CLI/system) is recorded in the
            // details instead of a second, duplicate audit write.
            AdminService::writeAudit(null, 'payout.period_closed', 'author_payouts', null, [
                'message' => 'Closed payout period '.$period,
                'period' => $period,
                'pool_bdt' => $pool,
                'distributed_bdt' => $distributed,
                'payouts_created' => $payoutsCreated,
            ]);

            return [
                'period' => $period,
                'gross_bdt' => $gross,
                'fee_bdt' => $fee,
                'net_bdt' => $netTotal,
                'pool_bdt' => $pool,
                'distributed_bdt' => $distributed,
                'remainder_bdt' => $remainder,
                'payouts_created' => $payoutsCreated,
            ];
        });
    }

    /** Approve a payout — Super Admin ONLY (spec §0.7, §2.4 step 2). */
    public function approvePayout(AuthorPayout $payout, User $admin): void
    {
        $this->requireSuperAdmin($admin);

        if ($payout->status !== AuthorPayout::STATUS_PENDING) {
            throw new InvalidArgumentException("Only a pending payout can be approved (status: {$payout->status}).");
        }

        $payout->update([
            'status' => AuthorPayout::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->notifyAuthor($payout, 'payout_approved', [
            'payout_id' => $payout->id, 'period' => $payout->period,
            'total_bdt' => (int) $payout->total_bdt,
        ]);
    }

    /**
     * Release a payout — Super Admin ONLY (spec §2.4 step 3). The Super
     * Admin sends the money by bKash Send Money OUTSIDE the system, then
     * records the payment TrxID here; payout_trx_id is REQUIRED for paid.
     */
    public function releasePayout(AuthorPayout $payout, User $admin, string $trxId): void
    {
        $this->requireSuperAdmin($admin);

        if ($payout->status !== AuthorPayout::STATUS_APPROVED) {
            throw new InvalidArgumentException("Only an approved payout can be released (status: {$payout->status}).");
        }

        $trxId = BillingService::normalizeTrxId($trxId);

        if (! BillingService::isValidTrxId($trxId)) {
            throw new InvalidArgumentException('A valid payout TrxID is required to release a payout.');
        }

        $payout->update([
            'status' => AuthorPayout::STATUS_PAID,
            'payout_trx_id' => $trxId,
            'paid_at' => now(),
        ]);

        $this->notifyAuthor($payout, 'payout_released', [
            'payout_id' => $payout->id, 'period' => $payout->period,
            'total_bdt' => (int) $payout->total_bdt,
        ]);
    }

    /** Mark a payout failed (e.g. wrong bKash/bank number) — Super Admin ONLY. */
    public function failPayout(AuthorPayout $payout, User $admin, string $reason): void
    {
        $this->requireSuperAdmin($admin);

        if (! in_array($payout->status, [AuthorPayout::STATUS_PENDING, AuthorPayout::STATUS_APPROVED], true)) {
            throw new InvalidArgumentException("Only a pending or approved payout can fail (status: {$payout->status}).");
        }

        $payout->update([
            'status' => AuthorPayout::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);

        $this->notifyAuthor($payout, 'payout_failed', [
            'payout_id' => $payout->id, 'period' => $payout->period,
            'total_bdt' => (int) $payout->total_bdt, 'reason' => $reason,
        ]);
    }

    /**
     * Retry a failed payout (spec §2.4 step 4) — Super Admin ONLY.
     * The row goes back to pending; the failure reason is KEPT as history.
     */
    public function retryFailedPayout(AuthorPayout $payout, User $admin): void
    {
        $this->requireSuperAdmin($admin);

        if ($payout->status !== AuthorPayout::STATUS_FAILED) {
            throw new InvalidArgumentException("Only a failed payout can be retried (status: {$payout->status}).");
        }

        $payout->update([
            'status' => AuthorPayout::STATUS_PENDING,
            'retry_count' => (int) $payout->retry_count + 1,
        ]);
    }

    /**
     * Author's accrued balance (spec §2.5): all earnings − all PAID payouts.
     * Always recomputed from the DB, in a transaction by the caller when
     * it gates a payout.
     */
    public function authorBalance(Author $author): int
    {
        $earned = (int) AuthorEarning::where('author_id', $author->id)->sum('amount_bdt');
        $paid = (int) AuthorPayout::where('author_id', $author->id)
            ->where('status', AuthorPayout::STATUS_PAID)
            ->sum('total_bdt');

        return $earned - $paid;
    }

    /**
     * A payout request is a NOTICE, not a gate (spec §2.4): the monthly
     * payout runs regardless. The notice goes to every Super Admin as an
     * in-app (database) notification when the notifications table exists
     * (Builder C, Phase 3 §7.15); without it the request is still accepted
     * and shown as requested. Returns true when a notice was stored.
     */
    public function recordPayoutRequest(Author $author, User $user): bool
    {
        if (! Schema::hasTable('notifications')) {
            return false;
        }

        $superAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->get();

        if ($superAdmins->isEmpty()) {
            return false;
        }

        foreach ($superAdmins as $admin) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\PayoutRequested',
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'data' => json_encode([
                    'author_id' => $author->id,
                    'author_name' => $author->name,
                    'requested_by' => $user->id,
                    'balance_bdt' => $this->authorBalance($author),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * The approved worked example (spec §2.6), computed FROM SETTINGS —
     * never hardcoded: 100 subscribers × plan price, fee per payment,
     * pool share, and the example 8.2% reading share.
     */
    public function workedExample(): array
    {
        $subscribers = 100;
        $price = $this->settingInt('subscription.price_bdt', 99);
        $feeRate = $this->settingInt('pool.fee_rate_pct', 2);
        $sharePct = $this->sharePct();

        $gross = $subscribers * $price;
        $feePerPayment = (int) round($price * $feeRate / 100);
        $fee = $subscribers * $feePerPayment;
        $net = $gross - $fee;
        $pool = (int) floor($net * $sharePct / 100);
        // The approved example author's counted share: 8.2% of all minutes.
        $exampleShare = (int) floor($pool * 82 / 1000);

        return [
            'subscribers' => $subscribers,
            'price_bdt' => $price,
            'gross_bdt' => $gross,
            'fee_bdt' => $fee,
            'net_bdt' => $net,
            'share_pct' => $sharePct,
            'pool_bdt' => $pool,
            'example_minutes_pct' => 8.2,
            'example_share_bdt' => $exampleShare,
            'fee_rate_pct' => $feeRate,
        ];
    }

    /** Counted minutes for one author in a period (for the pool page). */
    public function countedMinutesFor(Author $author, string $period): array
    {
        $rows = ReadingMinute::where('author_id', $author->id)
            ->where('period', $period)
            ->get();

        return [
            'minutes' => (int) $rows->sum('minutes'),
            'counted_minutes' => (int) $rows->where('counted', true)->sum('minutes'),
            'readers' => $rows->pluck('user_id')->unique()->count(),
        ];
    }

    /** Own-book minutes never count (spec §2.2 rule 4 — defensive check; Builder A enforces at write time too). */
    protected function isOwnBookMinutes(ReadingMinute $row): bool
    {
        $book = $row->book;

        if ($book === null || $book->author_id === null) {
            return false;
        }

        $authorUserId = Author::where('id', $book->author_id)->value('user_id');

        return $authorUserId !== null && (int) $authorUserId === (int) $row->user_id;
    }

    /**
     * In-app notification to the payout's author (database channel only,
     * spec §6). Never carries a TrxID or a full bKash number — only the
     * payout id, period and plain amount.
     */
    protected function notifyAuthor(AuthorPayout $payout, string $type, array $data = []): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $author = $payout->author;

        if ($author === null || $author->user_id === null) {
            return;
        }

        $user = User::query()->find($author->user_id);

        if ($user !== null) {
            $user->notify(new AuthorNotification($type, $data));
        }
    }

    protected function requireSuperAdmin(User $user): void
    {
        if (! AdminService::isSuperAdmin($user)) {
            throw new AuthorizationException('Only a Super Admin may approve, release, fail or retry a payout.');
        }
    }

    protected function payoutDestination(Author $author): ?string
    {
        if (($author->payout_method ?? 'bkash') === 'bank') {
            return $author->bank_account_number;
        }

        return $author->bkash_number;
    }

    protected function periodOf(Order $order): string
    {
        $at = $order->updated_at ?? now();

        return $at->format('Y-m');
    }

    protected function setSetting(string $key, string $value): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );
    }
}
