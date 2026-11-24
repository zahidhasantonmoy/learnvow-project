<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\FraudAlert;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PointTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserBook;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Billing seed data (spec §7, frozen values — keep exactly):
 * Premium plan ৳99/30d/7d trial; coupon EID26 20% applies_to=all;
 * gift code GIFT-7K2M9P → Pride and Prejudice; demo Reader wallet ৳226
 * + points 340; orders LV-2026-1041…1044.
 *
 * Demo payment numbers are NOT seeded here (spec A16): bKash/Nagad
 * numbers stay empty in settings until the owner sets real ones.
 */
class BillingSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Settings (§6.21). Payment numbers intentionally EMPTY. ----
        $settings = [
            'payment.bkash_number' => '',
            'payment.nagad_number' => '',
            'payment.fee_note' => '',
            'subscription.price_bdt' => '99',
            'referral.reward_type' => 'premium_week',
            'referral.reward_value' => '7',
            'points.per_purchase' => '10',
            'points.per_finish' => '20',
            'payment.sla_hours' => '12',
            // Phase 4 (§12.2) — intl, gateway seam, print, FCM, AI, API.
            'intl.price_bdt' => '199',
            'intl.plan_slug' => 'premium-monthly-intl',
            'intl.trial_days' => '7',
            'intl.currency' => 'BDT',
            'intl.available_countries' => '',
            'payments.gateway' => 'manual',
            'sslcommerz.store_id' => '',
            'sslcommerz.store_password' => '',
            'print.base_price_bdt' => '',
            'print.delivery_charge_bdt' => '',
            'print.partner_cost_bdt' => '',
            'print.author_discount_pct' => '',
            'fcm.enabled' => '0',
            'fcm.project_id' => '',
            'fcm.credentials_path' => 'storage/app/private/firebase-service-account.json',
            'ai.enabled' => '0',
            'ai.provider' => '',
            'api.rate_limit_per_minute' => '60',
        ];
        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        // ---- Subscription plan: Premium monthly ৳99, 30 days, 7-day trial. ----
        $plan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'premium-monthly'],
            ['name' => 'LearnVow Premium', 'name_en' => 'LearnVow Premium', 'price_bdt' => 99, 'duration_days' => 30, 'trial_days' => 7, 'is_active' => true]
        );

        // Phase 4 (§3.1) — Premium International ৳199/30d/7d trial.
        SubscriptionPlan::firstOrCreate(
            ['slug' => 'premium-monthly-intl'],
            ['name' => 'Premium International', 'name_en' => 'Premium International', 'price_bdt' => 199, 'duration_days' => 30, 'trial_days' => 7, 'is_active' => true]
        );

        // ---- Coupon EID26: 20% off, applies to all, no expiry/limits. ----
        $coupon = Coupon::firstOrCreate(
            ['code' => 'EID26'],
            ['percent' => 20, 'applies_to' => 'all', 'is_active' => true, 'max_uses' => null, 'max_uses_per_user' => 1, 'used_count' => 0]
        );

        $reader = User::where('phone', '01700000002')->firstOrFail();
        $admin = User::where('phone', '01700000001')->first();

        // Referral codes for demo users that lack one.
        foreach (User::whereNull('referral_code')->get() as $u) {
            $u->update(['referral_code' => 'LV'.str_pad((string) $u->id, 6, '0', STR_PAD_LEFT)]);
        }

        $pride = Book::where('slug', 'pride-and-prejudice')->firstOrFail();
        $prophet = Book::where('slug', 'the-prophet')->firstOrFail();

        // ---- Demo Reader wallet ৳226 + points 340 (opening ledger rows). ----
        $reader->update(['wallet_balance_bdt' => 226, 'points_balance' => 340]);
        WalletTransaction::updateOrInsert(
            ['user_id' => $reader->id, 'type' => 'topup', 'amount_bdt' => 226],
            ['balance_after_bdt' => 226, 'note' => 'demo opening balance', 'created_at' => now()]
        );
        PointTransaction::updateOrInsert(
            ['user_id' => $reader->id, 'type' => 'earn_purchase', 'points' => 340],
            ['balance_after' => 340, 'created_at' => now()]
        );

        // ---- LV-2026-1041: Pride and Prejudice ৳150, verified, paid. ----
        $order1041 = $this->seedOrder('LV-2026-1041', $reader, 'book', 'paid', 150, 0, 0, 150);
        $order1041->items()->updateOrCreate(
            ['book_id' => $pride->id],
            ['title_snapshot' => $pride->title, 'unit_price_bdt' => 150, 'list_price_bdt' => null, 'quantity' => 1]
        );
        $payment1041 = $this->seedPayment($order1041, $reader, 'bkash', '9HX7K2LMNP', 150, 'verified', $admin?->id, '2026-11-15 10:05:00');
        UserBook::firstOrCreate(
            ['user_id' => $reader->id, 'book_id' => $pride->id],
            ['source' => 'purchase']
        );
        $this->seedEvents($payment1041, $reader->id, ['submitted', 'verified', 'access_granted']);

        // ---- LV-2026-1042: Premium ৳99, verified; active sub 2026-11-15 → 2026-12-15. ----
        $order1042 = $this->seedOrder('LV-2026-1042', $reader, 'premium', 'paid', 99, 0, 0, 99);
        $order1042->items()->updateOrCreate(
            ['plan_id' => $plan->id],
            ['title_snapshot' => $plan->name, 'unit_price_bdt' => 99, 'list_price_bdt' => null, 'quantity' => 1]
        );
        $payment1042 = $this->seedPayment($order1042, $reader, 'bkash', '9HX7K2LMNQ', 99, 'verified', $admin?->id, '2026-11-15 10:10:00');
        $this->seedEvents($payment1042, $reader->id, ['submitted', 'verified', 'access_granted']);
        Subscription::updateOrCreate(
            ['user_id' => $reader->id, 'order_id' => $order1042->id],
            ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => '2026-11-15 10:10:00', 'ends_at' => '2026-12-15 10:10:00', 'trial_ends_at' => null]
        );

        // ---- LV-2026-1043: The Prophet ৳77 (EID26 on sale price 96 − 19). ----
        $order1043 = $this->seedOrder('LV-2026-1043', $reader, 'book', 'awaiting_verification', 96, $coupon->id, 19, 77);
        $order1043->items()->updateOrCreate(
            ['book_id' => $prophet->id],
            ['title_snapshot' => $prophet->title, 'unit_price_bdt' => 96, 'list_price_bdt' => 120, 'quantity' => 1]
        );
        CouponRedemption::firstOrCreate(
            ['coupon_id' => $coupon->id, 'user_id' => $reader->id, 'order_id' => $order1043->id],
            ['discount_bdt' => 19]
        );
        $coupon->increment('used_count');
        $payment1043 = $this->seedPayment($order1043, $reader, 'bkash', '9HW3PZ77AB', 77, 'awaiting_verification', null, '2026-11-22 09:30:00');
        $this->seedEvents($payment1043, $reader->id, ['submitted']);

        // ---- LV-2026-1044: duplicate TrxID 9HX7K2LMNP submitted again. ----
        $order1044 = $this->seedOrder('LV-2026-1044', $reader, 'book', 'duplicate_suspect', 96, 0, 0, 96);
        $order1044->items()->updateOrCreate(
            ['book_id' => $prophet->id],
            ['title_snapshot' => $prophet->title, 'unit_price_bdt' => 96, 'list_price_bdt' => 120, 'quantity' => 1]
        );
        $payment1044 = $this->seedPayment($order1044, $reader, 'bkash', '9HX7K2LMNP', 96, 'duplicate_suspect', null, '2026-11-22 11:00:00');
        $this->seedEvents($payment1044, $reader->id, ['duplicate_flagged']);
        FraudAlert::firstOrCreate(
            ['payment_id' => $payment1044->id],
            ['trx_id_normalized' => '9HX7K2LMNP', 'existing_payment_id' => $payment1041->id, 'status' => 'open']
        );

        // ---- Gift code GIFT-7K2M9P → Pride and Prejudice, active, unclaimed. ----
        // Gifted by the demo admin so the demo reader can claim it.
        DB::table('gift_codes')->updateOrInsert(
            ['code' => 'GIFT-7K2M9P'],
            [
                'book_id' => $pride->id,
                'buyer_id' => $admin?->id ?? $reader->id,
                'order_id' => $order1041->id,
                'claimed_by' => null,
                'status' => 'active',
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedOrder(string $no, User $user, string $type, string $status, int $subtotal, ?int $couponId, int $couponAmt, int $total): Order
    {
        return Order::updateOrCreate(
            ['order_no' => $no],
            [
                'user_id' => $user->id, 'type' => $type, 'status' => $status,
                'subtotal_bdt' => $subtotal, 'coupon_id' => $couponId ?: null,
                'coupon_amt_bdt' => $couponAmt, 'wallet_used_bdt' => 0, 'total_bdt' => $total,
            ]
        );
    }

    private function seedPayment(Order $order, User $user, string $method, string $trxId, int $amount, string $status, ?int $verifiedBy, string $submittedAt): Payment
    {
        return Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'user_id' => $user->id, 'method' => $method, 'trx_id' => $trxId,
                'trx_id_normalized' => strtoupper(trim($trxId)), 'amount_bdt' => $amount,
                'status' => $status, 'submitted_at' => $submittedAt,
                'verified_at' => $status === 'verified' ? $submittedAt : null,
                'verified_by' => $verifiedBy, 'fix_count' => 0,
            ]
        );
    }

    /** Append-only audit rows for a seeded payment (idempotent by action). */
    private function seedEvents(Payment $payment, int $actorId, array $actions): void
    {
        foreach ($actions as $action) {
            PaymentEvent::firstOrCreate(
                ['payment_id' => $payment->id, 'action' => $action],
                ['order_id' => $payment->order_id, 'actor_id' => $actorId]
            );
        }
    }
}
