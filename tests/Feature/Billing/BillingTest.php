<?php

namespace Tests\Feature\Billing;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FraudAlert;
use App\Models\GiftCode;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserBook;
use App\Services\BillingService;
use App\Services\BookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder B — billing rules (spec §2–§4, §13 billing rows).
 * Money is always int (BDT); coupon stacks on the SALE price (K150).
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;
    private User $reader;
    private User $admin;
    private Book $prophet;
    private Book $pride;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $author = Author::create(['name' => 'Test Author', 'slug' => 'test-author', 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);

        $this->prophet = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);
        $this->pride = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'Pride and Prejudice', 'slug' => 'pride-and-prejudice', 'description' => 'Pride.',
            'language' => 'en', 'price_bdt' => 150, 'list_price_bdt' => null, 'status' => 'published',
        ]);

        $this->plan = SubscriptionPlan::create([
            'slug' => 'premium-monthly', 'name' => 'LearnVow Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
        Coupon::create(['code' => 'EID26', 'percent' => 20, 'applies_to' => 'all', 'is_active' => true]);
        \DB::table('settings')->insert([
            ['key' => 'points.per_purchase', 'value' => '10'],
            ['key' => 'points.per_finish', 'value' => '20'],
            ['key' => 'payment.sla_hours', 'value' => '12'],
            ['key' => 'referral.reward_type', 'value' => 'premium_week'],
            ['key' => 'referral.reward_value', 'value' => '7'],
            ['key' => 'payment.bkash_number', 'value' => ''],
            ['key' => 'payment.nagad_number', 'value' => ''],
        ]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->admin = User::factory()->create(['phone' => '01700000001']);
        $this->billing = app(BillingService::class);
    }

    private function bookOrder(User $user, Book $book, ?string $coupon = null, int $walletUse = 0): Order
    {
        $priced = $this->billing->priceOrder([
            'user' => $user, 'type' => 'book', 'book_id' => $book->id,
            'coupon_code' => $coupon, 'wallet_use_bdt' => $walletUse,
        ]);

        return $this->billing->createOrder($user, $priced);
    }

    // ---- Pricing (spec §4.3, K150) ----

    public function test_prophet_with_eid26_is_77(): void
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id, 'coupon_code' => 'EID26',
        ]);

        $this->assertSame(96, $priced['subtotal_bdt']);
        $this->assertSame(19, $priced['coupon_amt_bdt']);
        $this->assertSame(77, $priced['total_bdt']);
    }

    public function test_prophet_without_coupon_is_96(): void
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
        ]);

        $this->assertSame(96, $priced['total_bdt']);
    }

    public function test_premium_with_eid26_is_79(): void
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'premium', 'plan_id' => $this->plan->id, 'coupon_code' => 'EID26',
        ]);

        $this->assertSame(99, $priced['subtotal_bdt']);
        $this->assertSame(20, $priced['coupon_amt_bdt']);
        $this->assertSame(79, $priced['total_bdt']);
    }

    public function test_client_tampered_price_is_ignored(): void
    {
        // A client-sent price/total must never change the server's math.
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
            'coupon_code' => 'EID26', 'total_bdt' => 1, 'subtotal_bdt' => 1, 'unit_price_bdt' => 1,
        ]);

        $this->assertSame(77, $priced['total_bdt']);
    }

    public function test_wallet_use_never_exceeds_balance(): void
    {
        $this->reader->update(['wallet_balance_bdt' => 50]);
        $priced = $this->billing->priceOrder([
            'user' => $this->reader->fresh(), 'type' => 'book', 'book_id' => $this->prophet->id,
            'coupon_code' => 'EID26', 'wallet_use_bdt' => 500,
        ]);

        $this->assertSame(50, $priced['wallet_used_bdt']);
        $this->assertSame(27, $priced['total_bdt']);
    }

    public function test_zero_total_order_is_granted_immediately(): void
    {
        $this->reader->update(['wallet_balance_bdt' => 200]);
        $order = $this->bookOrder($this->reader->fresh(), $this->prophet, null, 96);

        $this->assertSame(0, $order->total_bdt);
        $this->assertSame('paid', $order->status);
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());
    }

    // ---- TrxID flow (spec §3.1) ----

    public function test_trxid_submit_then_verify_grants_access(): void
    {
        $order = $this->bookOrder($this->reader, $this->prophet, 'EID26');
        $this->assertSame(77, $order->total_bdt);

        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->assertSame('awaiting_verification', $payment->status);
        $this->assertSame('9HX7K2LMNP', $payment->trx_id_normalized);
        // No access before verification.
        $this->assertFalse(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());
        $this->assertSame('awaiting_verification', $order->fresh()->status);

        $this->billing->verifyPayment($payment, $this->admin);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'action' => 'submitted']);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'action' => 'verified']);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'action' => 'access_granted']);
    }

    public function test_duplicate_trxid_is_flagged_and_never_auto_approved(): void
    {
        $order1 = $this->bookOrder($this->reader, $this->pride);
        $payment1 = $this->billing->submitTrxId($order1, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment1, $this->admin);

        $other = User::factory()->create(['phone' => '01700000009']);
        $order2 = $this->bookOrder($other, $this->prophet);
        $payment2 = $this->billing->submitTrxId($order2, 'bkash', ' 9hx7k2lmnp '); // lowercase + spaces

        $this->assertSame('duplicate_suspect', $payment2->status);
        $this->assertSame('duplicate_suspect', $order2->fresh()->status);
        $this->assertDatabaseHas('fraud_alerts', [
            'payment_id' => $payment2->id, 'existing_payment_id' => $payment1->id, 'status' => 'open',
        ]);
        $this->assertFalse(UserBook::where('user_id', $other->id)->where('book_id', $this->prophet->id)->exists());

        // Verify is refused until the fraud alert is decided.
        $this->expectException(InvalidArgumentException::class);
        $this->billing->verifyPayment($payment2, $this->admin);
    }

    public function test_fraud_decision_legitimate_returns_payment_to_verification(): void
    {
        $order1 = $this->bookOrder($this->reader, $this->pride);
        $payment1 = $this->billing->submitTrxId($order1, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment1, $this->admin);

        $other = User::factory()->create(['phone' => '01700000009']);
        $order2 = $this->bookOrder($other, $this->prophet);
        $payment2 = $this->billing->submitTrxId($order2, 'bkash', '9HX7K2LMNP');
        $alert = FraudAlert::where('payment_id', $payment2->id)->firstOrFail();

        $this->billing->decideFraud($alert, $this->admin, 'legitimate', 'Same family, one payment each.');

        $this->assertSame('awaiting_verification', $payment2->fresh()->status);
        $this->assertSame('legitimate', $alert->fresh()->status);
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment2->id, 'action' => 'fraud_decided']);
    }

    public function test_wrong_trxid_fix_three_times_then_blocked(): void
    {
        $order = $this->bookOrder($this->reader, $this->prophet);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HW3PZ77AB');

        $this->billing->correctTrxId($payment, '9HW3PZ77AC');
        $this->billing->correctTrxId($payment->fresh(), '9HW3PZ77AD');
        $fixed = $this->billing->correctTrxId($payment->fresh(), '9HW3PZ77AE');
        $this->assertSame(3, $fixed->fix_count);
        $this->assertSame('awaiting_verification', $fixed->status);
        // Old TrxID stays in the audit history.
        $this->assertDatabaseHas('payment_events', ['payment_id' => $payment->id, 'action' => 'trx_corrected']);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->correctTrxId($fixed, '9HW3PZ77AF');
    }

    // ---- Premium / trial (spec §2.2) ----

    public function test_trial_once_per_account(): void
    {
        $trial = $this->billing->startTrial($this->reader);
        $this->assertSame('trialing', $trial->status);
        $this->assertTrue($this->billing->hasActivePremium($this->reader->fresh()));
        $this->assertNotNull($this->reader->fresh()->trial_used_at);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->startTrial($this->reader->fresh());
    }

    public function test_premium_purchase_activates_subscription(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'premium', 'plan_id' => $this->plan->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNQ');

        $this->assertFalse($this->billing->hasActivePremium($this->reader));
        $this->billing->verifyPayment($payment, $this->admin);
        $this->assertTrue($this->billing->hasActivePremium($this->reader->fresh()));

        $sub = $this->billing->currentSubscription($this->reader);
        $this->assertSame('active', $sub->status);
        $this->assertSame($order->id, $sub->order_id);
    }

    public function test_cancel_runs_to_term_end_with_no_refund(): void
    {
        $trial = $this->billing->startTrial($this->reader);
        $endsAt = $trial->ends_at;
        $this->billing->cancelSubscription($trial->fresh(), 'too expensive');

        $cancelled = $trial->fresh();
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertTrue($cancelled->ends_at->equalTo($endsAt));
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseCount('wallet_transactions', 0); // no refund
    }

    public function test_bought_book_survives_premium_end(): void
    {
        $books = app(BookService::class);

        // Buy the book, then let Premium expire.
        $order = $this->bookOrder($this->reader, $this->prophet);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment, $this->admin);

        $trial = $this->billing->startTrial($this->reader->fresh());
        $trial->update(['status' => 'expired', 'ends_at' => now()->subDay(), 'trial_ends_at' => now()->subDay()]);

        $this->assertFalse($this->billing->hasActivePremium($this->reader->fresh()));
        // Bought book still readable; a Premium-only book is not.
        $this->assertTrue($books->canRead($this->reader->fresh(), $this->prophet));
        $this->assertFalse($books->canRead($this->reader->fresh(), $this->pride));
    }

    // ---- Wallet / points / referral credit (spec §3.4–3.5, X13) ----

    public function test_wallet_topup_and_referral_credit_line(): void
    {
        $this->billing->walletTopup($this->reader, 226, null, null, 'demo opening balance');
        $this->billing->referralCredit($this->reader->fresh(), 50);

        $this->assertSame(276, $this->reader->fresh()->wallet_balance_bdt);
        $this->assertSame(50, $this->billing->referralCreditTotal($this->reader));
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $this->reader->id, 'type' => 'referral_credit', 'amount_bdt' => 50, 'balance_after_bdt' => 276,
        ]);
    }

    public function test_wallet_never_goes_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->billing->walletSpend($this->reader, 10);
    }

    public function test_points_earn_uses_settings_rate(): void
    {
        $order = $this->bookOrder($this->reader, $this->prophet);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment, $this->admin);

        $this->assertSame(10, $this->reader->fresh()->points_balance); // points.per_purchase = 10
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $this->reader->id, 'type' => 'earn_purchase', 'points' => 10, 'balance_after' => 10,
        ]);
    }

    // ---- Gift (spec §3.5) ----

    public function test_gift_purchase_creates_code_and_redeem_grants_book_once(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'gift', 'book_id' => $this->pride->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment, $this->admin);

        $gift = GiftCode::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('active', $gift->status);
        $this->assertStringStartsWith('GIFT-', $gift->code);

        $claimer = User::factory()->create(['phone' => '01700000008']);
        $this->billing->redeemGift($gift->code, $claimer);
        $this->assertTrue(UserBook::where('user_id', $claimer->id)->where('book_id', $this->pride->id)->exists());
        $this->assertSame('claimed', $gift->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->redeemGift($gift->code, User::factory()->create(['phone' => '01700000007']));
    }

    public function test_gift_cannot_be_redeemed_by_buyer(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'gift', 'book_id' => $this->pride->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $this->billing->verifyPayment($payment, $this->admin);
        $gift = GiftCode::where('order_id', $order->id)->firstOrFail();

        $this->expectException(InvalidArgumentException::class);
        $this->billing->redeemGift($gift->code, $this->reader);
    }
}
