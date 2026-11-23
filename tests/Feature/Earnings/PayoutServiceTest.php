<?php

namespace Tests\Feature\Earnings;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReadingMinute;
use App\Models\ReadingProgress;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBook;
use App\Services\BillingService;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3 §2 — PayoutService: sale accrual, refund reversal, pool close,
 * payout gates. All money is int (৳).
 */
class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    private PayoutService $payout;

    private User $reader;

    private User $reader2;

    private User $admin;

    private User $superAdmin;

    private User $authorUser;

    private Author $author;

    private Author $author2;

    private Book $prophet;

    private Book $pride;

    private int $trxCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00'));

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);

        $this->authorUser = User::factory()->create(['phone' => '01700000003']);
        $this->authorUser->assignRole('author');
        $this->author = Author::create([
            'name' => 'Kahlil Gibran', 'slug' => 'kahlil-gibran',
            'user_id' => $this->authorUser->id, 'status' => 'approved',
        ]);
        $authorUser2 = User::factory()->create(['phone' => '01700000004']);
        $authorUser2->assignRole('author');
        $this->author2 = Author::create([
            'name' => 'Jane Austen', 'slug' => 'jane-austen',
            'user_id' => $authorUser2->id, 'status' => 'approved',
        ]);

        $this->prophet = Book::create([
            'author_id' => $this->author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);
        $this->pride = Book::create([
            'author_id' => $this->author2->id, 'category_id' => $category->id,
            'title' => 'Pride and Prejudice', 'slug' => 'pride-and-prejudice', 'description' => 'Pride.',
            'language' => 'en', 'price_bdt' => 150, 'list_price_bdt' => null, 'status' => 'published',
        ]);

        Coupon::create(['code' => 'EID26', 'percent' => 20, 'applies_to' => 'all', 'is_active' => true, 'max_uses_per_user' => 1]);

        foreach ([
            'pool.share_pct' => '80', 'pool.fee_rate_pct' => '2',
            'pool.count_min_minutes' => '10', 'pool.count_min_percent' => '10',
            'pool.min_payout_bdt' => '500', 'pool.payout_day' => '10',
            'pool.carry_bdt' => '0', 'subscription.price_bdt' => '99',
            'points.per_purchase' => '0',
        ] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->reader->assignRole('reader');
        $this->reader2 = User::factory()->create(['phone' => '01700000005']);
        $this->reader2->assignRole('reader');
        $this->admin = User::factory()->create(['phone' => '01700000001']);
        $this->admin->assignRole('admin');
        $this->superAdmin = User::factory()->create(['phone' => '01700000000']);
        $this->superAdmin->assignRole('super-admin');

        $this->billing = app(BillingService::class);
        $this->payout = app(PayoutService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---- helpers ----

    private function payForBook(User $user, Book $book, ?string $coupon = null): Order
    {
        $priced = $this->billing->priceOrder([
            'user' => $user, 'type' => 'book', 'book_id' => $book->id, 'coupon_code' => $coupon,
        ]);
        $order = $this->billing->createOrder($user, $priced);
        $this->trxCounter++;
        $payment = $this->billing->submitTrxId($order, 'bkash', 'TRX'.str_pad((string) $this->trxCounter, 7, '0', STR_PAD_LEFT));

        $this->billing->verifyPayment($payment, $this->admin);

        return $order->fresh();
    }

    /** A verified ৳99 premium payment in November 2026 (no BillingService flow — closePeriod reads payments/orders only). */
    private function premiumPayment(User $user, int $n): void
    {
        $order = Order::create([
            'order_no' => 'LV-2026-9'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
            'user_id' => $user->id, 'type' => 'premium', 'status' => 'paid',
            'subtotal_bdt' => 99, 'coupon_amt_bdt' => 0, 'wallet_used_bdt' => 0, 'total_bdt' => 99,
        ]);
        Payment::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'method' => 'bkash',
            'trx_id' => 'SUB'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'trx_id_normalized' => 'SUB'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'amount_bdt' => 99, 'status' => 'verified',
            'submitted_at' => '2026-11-10 09:00:00', 'verified_at' => '2026-11-10 09:00:00',
            'verified_by' => $this->admin->id,
        ]);
    }

    private function minutes(User $user, Book $book, Author $author, int $minutes, bool $flagged = false): ReadingMinute
    {
        return ReadingMinute::create([
            'user_id' => $user->id, 'book_id' => $book->id, 'author_id' => $author->id,
            'period' => '2026-11', 'minutes' => $minutes, 'flagged' => $flagged,
        ]);
    }

    // ---- §2.1 sale accrual ----

    public function test_prophet_96_sale_accrues_77_to_author(): void
    {
        $order = $this->payForBook($this->reader, $this->prophet);

        $earning = AuthorEarning::where('order_id', $order->id)->first();

        $this->assertNotNull($earning);
        $this->assertSame($this->author->id, $earning->author_id);
        $this->assertSame('sale', $earning->type);
        $this->assertSame(77, $earning->amount_bdt); // round(96 × 0.8) = 77
        $this->assertSame(80, $earning->share_pct);
        $this->assertSame('2026-11', $earning->period);
    }

    public function test_eid26_sale_77_accrues_62_to_author(): void
    {
        $order = $this->payForBook($this->reader, $this->prophet, 'EID26');

        $this->assertSame(77, $order->total_bdt);
        $this->assertSame(62, AuthorEarning::where('order_id', $order->id)->value('amount_bdt')); // round(77 × 0.8) = 62
    }

    public function test_no_accrual_before_verify_payment(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $this->billing->submitTrxId($order, 'bkash', 'TRXBEFORE01');

        $this->assertSame(0, AuthorEarning::where('order_id', $order->id)->count());

        $payment = $order->payments()->first();
        $this->billing->verifyPayment($payment, $this->admin);

        $this->assertSame(77, AuthorEarning::where('order_id', $order->id)->value('amount_bdt'));
    }

    public function test_accrue_sale_is_idempotent(): void
    {
        $order = $this->payForBook($this->reader, $this->prophet);

        $this->payout->accrueSale($order);
        $this->payout->accrueSale($order);

        $this->assertSame(1, AuthorEarning::where('order_id', $order->id)->where('type', 'sale')->count());
    }

    public function test_gift_order_accrues_to_author(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'gift', 'book_id' => $this->prophet->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', 'TRXGIFT001');
        $this->billing->verifyPayment($payment, $this->admin);

        $this->assertSame(77, AuthorEarning::where('order_id', $order->id)->value('amount_bdt'));
    }

    // ---- §2.1 refund reversal (A3) ----

    public function test_refund_reverses_earnings_and_buyer_keeps_access(): void
    {
        $order = $this->payForBook($this->reader, $this->prophet);

        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());

        $order->update(['refund_status' => 'requested']);
        $this->billing->refundOrder($order->fresh(), $this->superAdmin);

        $reversal = AuthorEarning::where('order_id', $order->id)->where('type', 'adjustment')->first();
        $this->assertNotNull($reversal);
        $this->assertSame(-77, $reversal->amount_bdt);
        $this->assertSame(0, $this->payout->authorBalance($this->author));

        // LOCKED from Phase 2: the refunded buyer KEEPS access.
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());
    }

    // ---- §2.2/§2.6 closePeriod — the worked example ----

    public function test_close_period_worked_example_100_subscribers(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            $this->premiumPayment($this->reader, $i);
        }

        // 8.2% of counted minutes for author 1, the rest for author 2.
        $this->minutes($this->reader, $this->prophet, $this->author, 82);
        $this->minutes($this->reader2, $this->pride, $this->author2, 918);

        $result = $this->payout->closePeriod('2026-11');

        // 100 × ৳99 = ৳9,900 gross; fee ৳2 per payment → ৳200; net ৳9,700; pool 80% → ৳7,760.
        $this->assertSame(9900, $result['gross_bdt']);
        $this->assertSame(200, $result['fee_bdt']);
        $this->assertSame(9700, $result['net_bdt']);
        $this->assertSame(7760, $result['pool_bdt']);
        $this->assertSame(2, $result['payouts_created']);

        // 8.2% → floor(7760 × 82/1000) = ৳636; the rest floor(7760 × 918/1000) = ৳7,123.
        $this->assertSame(636, AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->value('amount_bdt'));
        $this->assertSame(7123, AuthorEarning::where('author_id', $this->author2->id)->where('type', 'pool')->value('amount_bdt'));

        // Rounding remainder (7760 − 636 − 7123 = 1) carries to next month (A2).
        $this->assertSame(1, $result['remainder_bdt']);
        $this->assertSame('1', $this->payout->setting('pool.carry_bdt'));

        $payout1 = AuthorPayout::where('author_id', $this->author->id)->first();
        $this->assertSame(636, $payout1->total_bdt);
        $this->assertSame('pending', $payout1->status);

        $this->assertTrue(ReadingMinute::where('author_id', $this->author->id)->value('counted'));
    }

    public function test_close_period_twice_throws(): void
    {
        $this->minutes($this->reader, $this->prophet, $this->author, 20);
        $this->premiumPayment($this->reader, 1);
        $this->payout->closePeriod('2026-11');

        $this->expectException(InvalidArgumentException::class);
        $this->payout->closePeriod('2026-11');
    }

    public function test_carry_adds_to_next_month_pool(): void
    {
        Setting::where('key', 'pool.carry_bdt')->update(['value' => '1']);

        $this->premiumPayment($this->reader, 1); // one ৳99 payment: fee ৳2, net ৳97
        $this->minutes($this->reader, $this->prophet, $this->author, 20);

        $result = $this->payout->closePeriod('2026-11');

        // Pool = floor(97 × 0.8) + carry 1 = 77 + 1 = 78; sole author gets all 78.
        $this->assertSame(78, $result['pool_bdt']);
        $this->assertSame(78, AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->value('amount_bdt'));
        $this->assertSame(0, $result['remainder_bdt']);
    }

    // ---- §2.3 counting thresholds ----

    public function test_sub_10_minute_reads_do_not_count(): void
    {
        $row = $this->minutes($this->reader, $this->prophet, $this->author, 5);
        $this->premiumPayment($this->reader, 1);

        $this->payout->closePeriod('2026-11');

        $this->assertFalse($row->fresh()->counted);
        $this->assertNull(AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->first());
    }

    public function test_sub_10_minute_read_counts_when_progress_reaches_10_percent(): void
    {
        ReadingProgress::create([
            'user_id' => $this->reader->id, 'book_id' => $this->prophet->id,
            'percent' => 15, 'total_minutes' => 5,
        ]);
        $row = $this->minutes($this->reader, $this->prophet, $this->author, 5);
        $this->premiumPayment($this->reader, 1);

        $this->payout->closePeriod('2026-11');

        $this->assertTrue($row->fresh()->counted);
        $this->assertNotNull(AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->first());
    }

    public function test_own_book_minutes_never_count(): void
    {
        // The author reads their own book for 60 minutes.
        $row = $this->minutes($this->authorUser, $this->prophet, $this->author, 60);
        $this->premiumPayment($this->reader, 1);

        $this->payout->closePeriod('2026-11');

        $this->assertFalse($row->fresh()->counted);
        $this->assertNull(AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->first());
    }

    public function test_flagged_minutes_never_count(): void
    {
        $row = $this->minutes($this->reader, $this->prophet, $this->author, 60, true);
        $this->premiumPayment($this->reader, 1);

        $this->payout->closePeriod('2026-11');

        $this->assertFalse($row->fresh()->counted);
        $this->assertNull(AuthorEarning::where('author_id', $this->author->id)->where('type', 'pool')->first());
    }

    // ---- §2.5 minimum payout ----

    public function test_below_500_creates_no_payout_row_and_balance_carries(): void
    {
        $this->payForBook($this->reader, $this->prophet); // author earns ৳77

        $result = $this->payout->closePeriod('2026-11');

        $this->assertSame(0, $result['payouts_created']);
        $this->assertSame(0, AuthorPayout::where('author_id', $this->author->id)->count());
        $this->assertSame(77, $this->payout->authorBalance($this->author));
    }

    // ---- §2.4 payout gates ----

    private function makePayout(int $total = 1000): AuthorPayout
    {
        return AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-11',
            'sales_bdt' => $total, 'pool_bdt' => 0, 'total_bdt' => $total,
            'method' => 'bkash', 'destination' => '01700000210',
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_approve_payout(): void
    {
        $payout = $this->makePayout();

        $this->expectException(AuthorizationException::class);
        $this->payout->approvePayout($payout, $this->admin);
    }

    public function test_super_admin_approves_and_releases_with_trx_id(): void
    {
        $payout = $this->makePayout();

        $this->payout->approvePayout($payout, $this->superAdmin);
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame($this->superAdmin->id, $payout->fresh()->approved_by);

        // Paid requires a payout TrxID.
        $this->expectException(InvalidArgumentException::class);
        $this->payout->releasePayout($payout->fresh(), $this->superAdmin, '');
    }

    public function test_release_sets_paid_and_trx_id(): void
    {
        $payout = $this->makePayout();
        $this->payout->approvePayout($payout, $this->superAdmin);

        $this->payout->releasePayout($payout->fresh(), $this->superAdmin, 'bkash123456');

        $fresh = $payout->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('BKASH123456', $fresh->payout_trx_id);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_release_requires_approved_status(): void
    {
        $payout = $this->makePayout();

        $this->expectException(InvalidArgumentException::class);
        $this->payout->releasePayout($payout, $this->superAdmin, 'BKASH123456');
    }

    public function test_failed_payout_can_be_retried(): void
    {
        $payout = $this->makePayout();
        $this->payout->approvePayout($payout, $this->superAdmin);
        $this->payout->failPayout($payout->fresh(), $this->superAdmin, 'Bank account number was wrong');

        $failed = $payout->fresh();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('Bank account number was wrong', $failed->failure_reason);

        $this->payout->retryFailedPayout($failed, $this->superAdmin);

        $retried = $payout->fresh();
        $this->assertSame('pending', $retried->status);
        $this->assertSame(1, $retried->retry_count);
        // Failure history is NOT erased.
        $this->assertSame('Bank account number was wrong', $retried->failure_reason);
    }

    public function test_author_balance_is_earnings_minus_paid_payouts(): void
    {
        $this->payForBook($this->reader, $this->prophet); // +77
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 636, 'share_pct' => 80,
        ]);

        $this->assertSame(713, $this->payout->authorBalance($this->author));

        $payout = $this->makePayout(713);
        $this->payout->approvePayout($payout, $this->superAdmin);
        $this->payout->releasePayout($payout->fresh(), $this->superAdmin, 'BKASH123456');

        $this->assertSame(0, $this->payout->authorBalance($this->author));
    }

    // ---- §2.6 worked example (from settings) ----

    public function test_worked_example_matches_approved_figures(): void
    {
        $example = $this->payout->workedExample();

        $this->assertSame(9900, $example['gross_bdt']);
        $this->assertSame(200, $example['fee_bdt']);
        $this->assertSame(9700, $example['net_bdt']);
        $this->assertSame(7760, $example['pool_bdt']);
        $this->assertSame(636, $example['example_share_bdt']);
    }
}
