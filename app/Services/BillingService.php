<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\FraudAlert;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PointTransaction;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserBook;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Billing rules: pricing, orders, TrxID, wallet, points, coupons,
 * gifts, referrals, subscriptions. Spec §2–§4, §11 — Builder B owns
 * the methods inside this file (the coordinator only created it).
 *
 * Hard rules (spec §0, §4.3): all money is int (BDT); every amount is
 * recomputed server-side from the DB — never trust a client-sent price.
 * Coupon discount = round(sale_price * percent / 100) STACKED ON THE
 * SALE PRICE (K150: Prophet 96 - 19 = 77). The overnight list-price
 * coupon rule (120 - 24 = 96) is dead and must never be written.
 */
class BillingService
{
    public const MAX_TRX_FIXES = 3;

    /** Read a settings key (spec §6.21). Null when the table/key is absent. */
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

    /** Normalise a TrxID: trim + uppercase (spec §3.1). */
    public static function normalizeTrxId(string $trxId): string
    {
        return strtoupper(trim($trxId));
    }

    /** bKash/Nagad TrxIDs are 10-char alphanumeric; accept 8–12 to be safe. */
    public static function isValidTrxId(string $trxId): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{8,12}$/', self::normalizeTrxId($trxId));
    }

    /** The active Premium plan (single plan at launch: premium-monthly). */
    public function premiumPlan(): ?SubscriptionPlan
    {
        if (! Schema::hasTable('subscription_plans')) {
            return null;
        }

        return SubscriptionPlan::where('slug', 'premium-monthly')->where('is_active', true)->first()
            ?? SubscriptionPlan::where('is_active', true)->orderBy('id')->first();
    }

    /**
     * Validate a coupon against the DB. Returns the Coupon or null.
     * $appliesTo is 'books' | 'subscription' | 'all' for the item type.
     */
    public function findValidCoupon(?string $code, string $appliesTo, ?User $user = null): ?Coupon
    {
        if ($code === null || trim($code) === '' || ! Schema::hasTable('coupons')) {
            return null;
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if ($coupon === null || ! $coupon->is_active) {
            return null;
        }

        if ($coupon->applies_to !== 'all' && $coupon->applies_to !== $appliesTo) {
            return null;
        }

        $now = now();
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return null;
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return null;
        }
        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return null;
        }
        if ($user !== null && Schema::hasTable('coupon_redemptions')) {
            $usedByUser = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)->count();
            if ($usedByUser >= $coupon->max_uses_per_user) {
                return null;
            }
        }

        return $coupon;
    }

    /**
     * Coupon discount for a sale price (spec §4.3): round(sale * percent / 100).
     * 0 when the coupon is invalid for this item/user.
     */
    public function applyCoupon(string $code, int $salePrice, string $appliesTo, ?User $user = null): int
    {
        $coupon = $this->findValidCoupon($code, $appliesTo, $user);

        if ($coupon === null) {
            return 0;
        }

        return (int) round($salePrice * $coupon->percent / 100);
    }

    /**
     * The single source of truth for checkout math (spec §4.3).
     *
     * $input: user (User), type ('book'|'premium'|'gift'|'wallet_topup'),
     *   book_id?, plan_id?, coupon_code?, wallet_use_bdt?, topup_amount_bdt?
     *   Any client-sent price/total in $input is IGNORED (Cartup lesson).
     *
     * Returns: type, book/plan, subtotal_bdt, coupon (+id), coupon_amt_bdt,
     *   wallet_used_bdt, total_bdt, items[] (title, unit_price, list_price).
     */
    public function priceOrder(array $input): array
    {
        $user = $input['user'];
        $type = $input['type'];
        $couponCode = isset($input['coupon_code']) ? strtoupper(trim((string) $input['coupon_code'])) : null;
        if ($couponCode === '') {
            $couponCode = null;
        }

        $subtotal = 0;
        $items = [];
        $appliesTo = 'books';
        $book = null;
        $plan = null;

        if ($type === 'wallet_topup') {
            $amount = max(0, (int) ($input['topup_amount_bdt'] ?? 0));
            $subtotal = $amount;
            $items[] = ['title_snapshot' => 'Wallet top-up', 'unit_price_bdt' => $amount, 'list_price_bdt' => null, 'book_id' => null, 'plan_id' => null, 'quantity' => 1];
        } elseif ($type === 'premium') {
            $plan = isset($input['plan_id'])
                ? SubscriptionPlan::find($input['plan_id'])
                : $this->premiumPlan();
            if ($plan === null) {
                throw new InvalidArgumentException('No active subscription plan.');
            }
            // Price comes from the plan row — the DB price, never the client.
            $price = (int) $plan->price_bdt;
            $subtotal = $price;
            $appliesTo = 'subscription';
            $items[] = ['title_snapshot' => $plan->name, 'unit_price_bdt' => $price, 'list_price_bdt' => null, 'book_id' => null, 'plan_id' => $plan->id, 'quantity' => 1];
        } else {
            // book / gift: DB sale price only.
            $book = Book::findOrFail($input['book_id']);
            $subtotal = (int) $book->price_bdt;
            $items[] = [
                'title_snapshot' => $book->title,
                'unit_price_bdt' => (int) $book->price_bdt,
                'list_price_bdt' => $book->list_price_bdt,
                'book_id' => $book->id,
                'plan_id' => null,
                'quantity' => 1,
            ];
        }

        $coupon = $couponCode !== null ? $this->findValidCoupon($couponCode, $appliesTo, $user) : null;
        $couponAmt = $coupon !== null ? (int) round($subtotal * $coupon->percent / 100) : 0;

        $requestedWallet = max(0, (int) ($input['wallet_use_bdt'] ?? 0));
        $balance = (int) ($user->wallet_balance_bdt ?? 0);
        $walletUsed = min($requestedWallet, $balance, max(0, $subtotal - $couponAmt));

        $total = max(0, $subtotal - $couponAmt - $walletUsed);

        return [
            'type' => $type,
            'book' => $book,
            'plan' => $plan,
            'subtotal_bdt' => $subtotal,
            'coupon' => $coupon,
            'coupon_id' => $coupon?->id,
            'coupon_amt_bdt' => $couponAmt,
            'wallet_used_bdt' => $walletUsed,
            'total_bdt' => $total,
            'items' => $items,
        ];
    }

    /** Next order number: LV-YYYY-NNNN, sequential from 1041 (spec §6.2). */
    public function nextOrderNo(): string
    {
        $year = (int) now()->format('Y');
        $max = Order::where('order_no', 'like', "LV-{$year}-%")
            ->get()->map(fn (Order $o) => (int) substr((string) $o->order_no, -4))->max();
        $next = max(1041, ($max ?? 1040) + 1);

        return sprintf('LV-%d-%04d', $year, $next);
    }

    /**
     * Create the order + items from a priced array. Wallet spend is taken
     * here (ledger, in the same DB transaction); it is refunded to the
     * wallet if the payment is later rejected (rejectPayment).
     * A ৳0 total is granted immediately — no TrxID needed (spec §4.3).
     */
    public function createOrder(User $user, array $priced): Order
    {
        return DB::transaction(function () use ($user, $priced) {
            $order = Order::create([
                'order_no' => $this->nextOrderNo(),
                'user_id' => $user->id,
                'type' => $priced['type'],
                'status' => $priced['total_bdt'] === 0 ? 'paid' : 'pending_payment',
                'subtotal_bdt' => $priced['subtotal_bdt'],
                'coupon_id' => $priced['coupon_id'],
                'coupon_amt_bdt' => $priced['coupon_amt_bdt'],
                'wallet_used_bdt' => $priced['wallet_used_bdt'],
                'total_bdt' => $priced['total_bdt'],
            ]);

            foreach ($priced['items'] as $item) {
                $order->items()->create([
                    'book_id' => $item['book_id'],
                    'plan_id' => $item['plan_id'],
                    'title_snapshot' => $item['title_snapshot'],
                    'unit_price_bdt' => $item['unit_price_bdt'],
                    'list_price_bdt' => $item['list_price_bdt'],
                    'quantity' => $item['quantity'] ?? 1,
                ]);
            }

            if ($priced['coupon'] !== null && $priced['coupon_amt_bdt'] > 0) {
                CouponRedemption::create([
                    'coupon_id' => $priced['coupon']->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_bdt' => $priced['coupon_amt_bdt'],
                ]);
                $priced['coupon']->increment('used_count');
            }

            if ($priced['wallet_used_bdt'] > 0) {
                $this->walletSpend($user->fresh(), $priced['wallet_used_bdt'], 'orders', $order->id, 'Checkout — wallet payment');
            }

            if ($priced['total_bdt'] === 0) {
                $this->grantAccess($order, null);
            }

            return $order->fresh();
        });
    }

    /** Find any earlier payment with this normalised TrxID (any status). */
    public function checkDuplicateTrxId(string $normalized): ?Payment
    {
        return Payment::where('trx_id_normalized', self::normalizeTrxId($normalized))
            ->orderBy('id')->first();
    }

    public function logEvent(Payment $payment, string $action, ?int $actorId = null, ?string $note = null): PaymentEvent
    {
        return PaymentEvent::create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'actor_id' => $actorId,
            'action' => $action,
            'note' => $note,
        ]);
    }

    /**
     * Submit a TrxID for an order. Duplicate TrxID (any prior payment,
     * any status) → payment + order flagged duplicate_suspect, a
     * fraud_alerts row is opened, NO access is granted (spec §3.3).
     */
    public function submitTrxId(Order $order, string $method, string $trxId): Payment
    {
        if (! in_array($method, ['bkash', 'nagad'], true)) {
            throw new InvalidArgumentException('Payment method must be bKash or Nagad.');
        }
        if (! self::isValidTrxId($trxId)) {
            throw new InvalidArgumentException('TrxID format is invalid.');
        }
        if ($order->total_bdt === 0) {
            throw new InvalidArgumentException('This order needs no TrxID — it is already paid.');
        }
        if ($order->status === 'paid') {
            throw new InvalidArgumentException('This order is already paid.');
        }

        $normalized = self::normalizeTrxId($trxId);

        return DB::transaction(function () use ($order, $method, $trxId, $normalized) {
            $existing = $this->checkDuplicateTrxId($normalized);

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'method' => $method,
                'trx_id' => trim($trxId),
                'trx_id_normalized' => $normalized,
                'amount_bdt' => $order->total_bdt,
                'status' => $existing !== null ? 'duplicate_suspect' : 'awaiting_verification',
                'submitted_at' => now(),
            ]);

            if ($existing !== null) {
                $order->update(['status' => 'duplicate_suspect']);
                FraudAlert::create([
                    'trx_id_normalized' => $normalized,
                    'payment_id' => $payment->id,
                    'existing_payment_id' => $existing->id,
                    'status' => 'open',
                ]);
                $this->logEvent($payment, 'duplicate_flagged', $order->user_id,
                    "TrxID matches earlier payment #{$existing->id}.");
            } else {
                $order->update(['status' => 'awaiting_verification']);
                $this->logEvent($payment, 'submitted', $order->user_id);
            }

            return $payment->fresh();
        });
    }

    /**
     * Admin verifies a payment: order → paid, access granted
     * (user_books row for books, subscription activation for Premium,
     * wallet credit for top-ups, gift code for gifts). Never call on a
     * duplicate_suspect payment — the fraud decision comes first.
     */
    public function verifyPayment(Payment $payment, User $admin): void
    {
        if ($payment->status === 'duplicate_suspect') {
            throw new InvalidArgumentException('Decide the fraud alert first — a duplicate_suspect payment cannot be verified.');
        }
        if ($payment->status === 'verified') {
            return;
        }

        DB::transaction(function () use ($payment, $admin) {
            $payment->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);
            $this->logEvent($payment, 'verified', $admin->id);

            $order = $payment->order;
            $order->update(['status' => 'paid']);

            $this->grantAccess($order, $payment);
            $this->logEvent($payment, 'access_granted', $admin->id);
        });
    }

    /** Grant what a paid order buys. Shared by verifyPayment and ৳0 orders. */
    protected function grantAccess(Order $order, ?Payment $payment): void
    {
        $user = $order->user;

        if ($order->type === 'book') {
            $bookId = $order->items()->whereNotNull('book_id')->value('book_id');
            if ($bookId !== null) {
                UserBook::firstOrCreate(
                    ['user_id' => $user->id, 'book_id' => $bookId],
                    ['source' => 'purchase']
                );
                $this->pointsEarn($user->fresh(), $this->settingInt('points.per_purchase', 0), 'earn_purchase', 'orders', $order->id);
            }
        } elseif ($order->type === 'premium') {
            $this->activateSubscription($order);
        } elseif ($order->type === 'gift') {
            $this->createGift($order);
        } elseif ($order->type === 'wallet_topup') {
            $this->walletTopup($user->fresh(), $order->total_bdt, 'orders', $order->id, 'Wallet top-up');
        }
    }

    public function rejectPayment(Payment $payment, User $admin, string $note): void
    {
        if ($payment->status === 'verified') {
            throw new InvalidArgumentException('A verified payment cannot be rejected.');
        }

        DB::transaction(function () use ($payment, $admin, $note) {
            $payment->update(['status' => 'rejected', 'admin_note' => $note]);
            $this->logEvent($payment, 'rejected', $admin->id, $note);

            $order = $payment->order;
            $order->update(['status' => 'failed']);

            // Wallet money spent on this order goes back to the wallet.
            if ($order->wallet_used_bdt > 0) {
                $this->walletTopup($order->user->fresh(), $order->wallet_used_bdt, 'orders', $order->id, 'Refund — rejected payment');
            }
        });
    }

    /**
     * Approve a refund request (spec §3.6): Super Admin only. Only a
     * `paid` order whose refund_status is `requested` or `under_review`
     * can be refunded — a second refund throws. Everything the buyer
     * paid goes back to their WALLET as type=refund (never cash):
     * the cash total AND the wallet portion spent on the order
     * (wallet_used_bdt was deducted at order creation, so a refund
     * that returned only total_bdt would leave the buyer short).
     * Same DB transaction as the order update. Access is NOT revoked:
     * the spec is silent on that, so user_books / subscriptions are
     * left untouched (open question).
     */
    public function refundOrder(Order $order, User $admin): void
    {
        if (! AdminService::isSuperAdmin($admin)) {
            throw new AuthorizationException('Only a Super Admin may approve a refund.');
        }
        if ($order->status !== 'paid' || ! in_array($order->refund_status, ['requested', 'under_review'], true)) {
            throw new InvalidArgumentException("Order [{$order->id}] with status [{$order->status}] and refund_status [{$order->refund_status}] cannot be refunded.");
        }

        DB::transaction(function () use ($order, $admin) {
            $buyer = $order->user->fresh();

            // Full refund = cash total + wallet portion spent on the order.
            $refundAmount = (int) $order->total_bdt + (int) $order->wallet_used_bdt;

            // A ৳0 order paid nothing — there is nothing to credit.
            if ($refundAmount > 0) {
                $this->walletTopup($buyer, $refundAmount, 'orders', $order->id, 'Refund — order '.$order->order_no, 'refund');
            }

            $order->update(['status' => 'refunded', 'refund_status' => 'approved']);

            // payment_events.payment_id is NOT nullable (migration
            // 2026_11_22_210006), so a payment-less order (e.g. ৳0)
            // gets no event row rather than a bad FK.
            $payment = $order->payments()->orderByDesc('id')->first();
            if ($payment !== null) {
                $this->logEvent($payment, 'refunded', $admin->id,
                    "Refund approved — ৳{$refundAmount} returned to wallet.");
            }
        });
    }

    /**
     * Wrong-TrxID self-fix (X76, spec §3.2): only while the payment is
     * awaiting_verification or failed, max 3 fixes per payment. The old
     * TrxID stays in payment_events history. Re-runs the duplicate check.
     */
    public function correctTrxId(Payment $payment, string $newTrxId): Payment
    {
        if (! in_array($payment->status, ['awaiting_verification', 'failed'], true)) {
            throw new InvalidArgumentException('This TrxID can no longer be corrected.');
        }
        if ($payment->fix_count >= self::MAX_TRX_FIXES) {
            throw new InvalidArgumentException('TrxID correction limit reached (3). Please contact support.');
        }
        if (! self::isValidTrxId($newTrxId)) {
            throw new InvalidArgumentException('TrxID format is invalid.');
        }

        $normalized = self::normalizeTrxId($newTrxId);

        return DB::transaction(function () use ($payment, $normalized, $newTrxId) {
            $oldTrx = $payment->trx_id_normalized;
            $existing = $this->checkDuplicateTrxId($normalized);
            $isDuplicate = $existing !== null && $existing->id !== $payment->id;

            $payment->update([
                'trx_id' => trim($newTrxId),
                'trx_id_normalized' => $normalized,
                'fix_count' => $payment->fix_count + 1,
                'status' => $isDuplicate ? 'duplicate_suspect' : 'awaiting_verification',
            ]);
            $this->logEvent($payment, 'trx_corrected', $payment->user_id,
                "TrxID corrected ({$oldTrx} → {$normalized}).");

            $order = $payment->order;
            if ($isDuplicate) {
                $order->update(['status' => 'duplicate_suspect']);
                FraudAlert::create([
                    'trx_id_normalized' => $normalized,
                    'payment_id' => $payment->id,
                    'existing_payment_id' => $existing->id,
                    'status' => 'open',
                ]);
                $this->logEvent($payment, 'duplicate_flagged', $payment->user_id,
                    "Corrected TrxID matches earlier payment #{$existing->id}.");
            } else {
                $order->update(['status' => 'awaiting_verification']);
            }

            return $payment->fresh();
        });
    }

    /**
     * Admin decides a fraud alert (spec §3.3). 'legitimate' returns the
     * payment to awaiting_verification; 'mistake' / 'confirmed_fraud'
     * reject it (and refund any wallet spend). No access until decided.
     */
    public function decideFraud(FraudAlert $alert, User $admin, string $decision, ?string $note = null): void
    {
        if (! in_array($decision, ['confirmed_fraud', 'mistake', 'legitimate'], true)) {
            throw new InvalidArgumentException('Unknown fraud decision.');
        }
        if ($alert->status !== 'open') {
            throw new InvalidArgumentException('This fraud alert was already decided.');
        }

        DB::transaction(function () use ($alert, $admin, $decision, $note) {
            $alert->update([
                'status' => $decision,
                'decided_by' => $admin->id,
                'decision_note' => $note,
            ]);

            $payment = $alert->payment;
            $this->logEvent($payment, 'fraud_decided', $admin->id,
                "Fraud decision: {$decision}. ".($note ?? ''));

            if ($decision === 'legitimate') {
                $payment->update(['status' => 'awaiting_verification']);
                $payment->order->update(['status' => 'awaiting_verification']);
            } else {
                $payment->update(['status' => 'rejected']);
                $payment->order->update(['status' => 'failed']);
                $order = $payment->order;
                if ($order->wallet_used_bdt > 0) {
                    $this->walletTopup($order->user->fresh(), $order->wallet_used_bdt, 'orders', $order->id, 'Refund — fraud decision');
                }
            }
        });
    }

    /** 7-day trial, once per account (users.trial_used_at, spec §2.2/A6). */
    public function startTrial(User $user): Subscription
    {
        if ($user->trial_used_at !== null) {
            throw new InvalidArgumentException('The free trial was already used on this account.');
        }
        if ($this->hasActivePremium($user)) {
            throw new InvalidArgumentException('Premium is already active on this account.');
        }
        $plan = $this->premiumPlan();
        if ($plan === null) {
            throw new InvalidArgumentException('No active subscription plan.');
        }

        return DB::transaction(function () use ($user, $plan) {
            $now = now();
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => null,
                'status' => 'trialing',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($plan->trial_days),
                'trial_ends_at' => $now->copy()->addDays($plan->trial_days),
            ]);
            $user->update(['trial_used_at' => $now, 'status' => User::STATUS_TRIAL]);

            return $subscription;
        });
    }

    /** Activate (or renew) Premium from a paid premium order. */
    public function activateSubscription(Order $order): Subscription
    {
        $planId = $order->items()->whereNotNull('plan_id')->value('plan_id');
        $plan = $planId !== null ? SubscriptionPlan::find($planId) : $this->premiumPlan();
        if ($plan === null) {
            throw new InvalidArgumentException('No subscription plan for this order.');
        }

        return DB::transaction(function () use ($order, $plan) {
            $user = $order->user;
            $now = now();

            // A current trialing/active subscription is replaced by the new term.
            Subscription::where('user_id', $user->id)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->update(['status' => 'expired']);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $order->id,
                'status' => 'active',
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($plan->duration_days),
                'trial_ends_at' => null,
            ]);
            $user->update(['status' => User::STATUS_ACTIVE]);

            return $subscription;
        });
    }

    /** Cancel runs to the end of the term — no refund (spec §2.2). */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): void
    {
        if (! in_array($subscription->status, ['trialing', 'active', 'past_due'], true)) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        // ends_at is untouched: Premium runs to the end of the term.
    }

    /** The user's current (not yet ended) subscription, if any. */
    public function currentSubscription(User $user): ?Subscription
    {
        if (! Schema::hasTable('subscriptions')) {
            return null;
        }

        return Subscription::where('user_id', $user->id)
            ->whereIn('status', ['trialing', 'active', 'cancelled', 'past_due'])
            ->orderByDesc('ends_at')->first();
    }

    /** Premium trialing/active/cancelled-to-term-end and not past its end (spec §2.2). */
    public function hasActivePremium(User $user): bool
    {
        $subscription = $this->currentSubscription($user);
        if ($subscription === null || ! in_array($subscription->status, ['trialing', 'active', 'cancelled'], true)) {
            return false;
        }

        // Cancelled runs to end of term (§2.2) — no refund, access stays.
        $end = $subscription->status === 'trialing'
            ? ($subscription->trial_ends_at ?? $subscription->ends_at)
            : $subscription->ends_at;

        return $end !== null && $end->isFuture();
    }

    // ---- Wallet (spec §3.4): cache + ledger, always in a DB transaction. ----

    public function walletTopup(User $user, int $amount, ?string $referenceType = null, ?int $referenceId = null, ?string $note = null, string $type = 'topup'): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Top-up amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $note, $type) {
            $fresh = $user->fresh();
            $balance = (int) $fresh->wallet_balance_bdt + $amount;
            $fresh->update(['wallet_balance_bdt' => $balance]);

            return WalletTransaction::create([
                'user_id' => $fresh->id,
                'type' => $type,
                'amount_bdt' => $amount,
                'balance_after_bdt' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    public function walletSpend(User $user, int $amount, ?string $referenceType = null, ?int $referenceId = null, ?string $note = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Spend amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $note) {
            $fresh = $user->fresh();
            $balance = (int) $fresh->wallet_balance_bdt;
            if ($amount > $balance) {
                throw new InvalidArgumentException('Wallet balance is not enough.');
            }
            $balance -= $amount;
            $fresh->update(['wallet_balance_bdt' => $balance]);

            return WalletTransaction::create([
                'user_id' => $fresh->id,
                'type' => 'purchase',
                'amount_bdt' => -$amount,
                'balance_after_bdt' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
            ]);
        });
    }

    /**
     * Referral credit (X13): a wallet ledger line of type referral_credit —
     * shown as a separate line in the wallet ("in-app spend only"), never
     * cash, never withdrawable/transferable.
     */
    public function referralCredit(User $user, int $amount, ?string $note = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Referral credit must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $note) {
            $fresh = $user->fresh();
            $balance = (int) $fresh->wallet_balance_bdt + $amount;
            $fresh->update(['wallet_balance_bdt' => $balance]);

            return WalletTransaction::create([
                'user_id' => $fresh->id,
                'type' => 'referral_credit',
                'amount_bdt' => $amount,
                'balance_after_bdt' => $balance,
                'reference_type' => 'referrals',
                'reference_id' => null,
                'note' => $note ?? 'Referral credit — in-app spend only',
            ]);
        });
    }

    /** Total referral credit ever received (for the separate wallet line). */
    public function referralCreditTotal(User $user): int
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return 0;
        }

        return (int) WalletTransaction::where('user_id', $user->id)
            ->where('type', 'referral_credit')->sum('amount_bdt');
    }

    // ---- Points (spec §3.5): rates come from settings, never hardcoded. ----

    public function pointsEarn(User $user, int $points, string $type, ?string $referenceType = null, ?int $referenceId = null): ?PointTransaction
    {
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $points, $type, $referenceType, $referenceId) {
            $fresh = $user->fresh();
            $balance = (int) $fresh->points_balance + $points;
            $fresh->update(['points_balance' => $balance]);

            return PointTransaction::create([
                'user_id' => $fresh->id,
                'type' => $type,
                'points' => $points,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    public function pointsRedeem(User $user, int $points, ?string $referenceType = null, ?int $referenceId = null): PointTransaction
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to redeem must be positive.');
        }

        return DB::transaction(function () use ($user, $points, $referenceType, $referenceId) {
            $fresh = $user->fresh();
            if ($points > (int) $fresh->points_balance) {
                throw new InvalidArgumentException('Not enough points.');
            }
            $balance = (int) $fresh->points_balance - $points;
            $fresh->update(['points_balance' => $balance]);

            return PointTransaction::create([
                'user_id' => $fresh->id,
                'type' => 'redeem',
                'points' => -$points,
                'balance_after' => $balance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    // ---- Gifts (spec §3.5) ----

    public function createGift(Order $order): GiftCode
    {
        $bookId = $order->items()->whereNotNull('book_id')->value('book_id');
        if ($bookId === null) {
            throw new InvalidArgumentException('Gift orders need a book.');
        }

        do {
            $code = 'GIFT-'.Str::upper(Str::random(6));
        } while (GiftCode::where('code', $code)->exists());

        return GiftCode::create([
            'code' => $code,
            'book_id' => $bookId,
            'buyer_id' => $order->user_id,
            'order_id' => $order->id,
            'status' => 'active',
        ]);
    }

    /** Claim a gift code: one code once; never your own book / your own gift. */
    public function redeemGift(string $code, User $user): GiftCode
    {
        $gift = GiftCode::where('code', strtoupper(trim($code)))->first();
        if ($gift === null) {
            throw new InvalidArgumentException('Gift code not found.');
        }
        if ($gift->status !== 'active' || $gift->claimed_by !== null) {
            throw new InvalidArgumentException('This gift code was already claimed.');
        }
        if ($gift->buyer_id === $user->id) {
            throw new InvalidArgumentException('You cannot redeem your own gift.');
        }
        $book = $gift->book;
        // The demo author account is linked via authors.user_id (no User->author relation).
        if ($book !== null && Schema::hasTable('authors')) {
            $authorId = DB::table('authors')->where('user_id', $user->id)->value('id');
            if ($authorId !== null && (int) $book->author_id === (int) $authorId) {
                throw new InvalidArgumentException('An author cannot claim their own book as a gift.');
            }
        }

        return DB::transaction(function () use ($gift, $user) {
            $gift->update(['status' => 'claimed', 'claimed_by' => $user->id]);
            UserBook::firstOrCreate(
                ['user_id' => $user->id, 'book_id' => $gift->book_id],
                ['source' => 'gift']
            );

            return $gift->fresh();
        });
    }

    // ---- Referrals (spec §3.5) ----

    /** Record that $referee joined with $referrer's code (once per referee). */
    public function recordReferral(User $referrer, User $referee, string $codeUsed): Referral
    {
        return Referral::firstOrCreate(
            ['referee_id' => $referee->id],
            ['referrer_id' => $referrer->id, 'code_used' => $codeUsed, 'status' => 'joined']
        );
    }

    /**
     * Reward a qualified referral. Reward type/amount come from settings
     * (referral.reward_type / referral.reward_value, spec A13) — default
     * is 1 week free Premium (prototype copy). The settings row is the
     * source; the seed only sets the default.
     */
    public function rewardReferral(Referral $referral): void
    {
        if ($referral->status === 'rewarded') {
            return;
        }

        $type = $this->setting('referral.reward_type', 'premium_week');
        $value = $this->settingInt('referral.reward_value', 7);

        DB::transaction(function () use ($referral, $type, $value) {
            $referrer = $referral->referrer;
            if ($type === 'wallet_credit') {
                $this->referralCredit($referrer->fresh(), $value, 'Referral reward');
            } else {
                // Default: free Premium days — extend the current term, or
                // start a short active term when there is none.
                $plan = $this->premiumPlan();
                if ($plan !== null) {
                    $current = $this->currentSubscription($referrer);
                    $now = now();
                    if ($current !== null && in_array($current->status, ['trialing', 'active'], true) && $current->ends_at->isFuture()) {
                        $current->update(['ends_at' => $current->ends_at->copy()->addDays($value)]);
                    } else {
                        Subscription::create([
                            'user_id' => $referrer->id,
                            'plan_id' => $plan->id,
                            'order_id' => null,
                            'status' => 'active',
                            'starts_at' => $now,
                            'ends_at' => $now->copy()->addDays($value),
                        ]);
                    }
                }
            }

            $referral->update(['status' => 'rewarded', 'rewarded_at' => now()]);
        });
    }
}
