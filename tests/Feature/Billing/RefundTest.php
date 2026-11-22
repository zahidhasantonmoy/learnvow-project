<?php

namespace Tests\Feature\Billing;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserBook;
use App\Models\WalletTransaction;
use App\Services\AdminService;
use App\Services\BillingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 2 fixer — BillingService::refundOrder() (spec §3.6).
 *
 * - Super Admin only; Admin / Reader get AuthorizationException.
 * - Only a `paid` order with refund_status `requested`/`under_review`
 *   can be refunded; a second refund throws.
 * - The order total goes back to the buyer's WALLET as type=refund
 *   (never cash), in the same transaction as the order update.
 * - payment_events logs action `refunded` (actor = the admin).
 * - Access is NOT revoked on refund (spec is silent — open question).
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    private User $reader;

    private User $admin;

    private User $superAdmin;

    private Book $prophet;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $author = Author::create(['name' => 'Test Author', 'slug' => 'test-author', 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);

        $this->prophet = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);

        \DB::table('settings')->insert([
            ['key' => 'points.per_purchase', 'value' => '10'],
        ]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        AdminService::assignRole($this->reader, 'reader');

        $this->admin = User::factory()->create(['phone' => '01700000001']);
        AdminService::assignRole($this->admin, 'admin');

        $this->superAdmin = User::factory()->create(['phone' => '01700000003']);
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->billing = app(BillingService::class);
    }

    /** A paid book order (TrxID submitted + verified) with a refund requested. */
    private function paidOrderWithRefundRequested(): Order
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
        ]);
        $order = $this->billing->createOrder($this->reader, $priced);

        $payment = $this->billing->submitTrxId($order, 'bkash', 'ABC1234567');
        $this->billing->verifyPayment($payment, $this->admin);

        $order->fresh()->update([
            'refund_requested_at' => now(),
            'refund_status' => 'requested',
        ]);

        return $order->fresh();
    }

    public function test_super_admin_approves_refund_to_wallet(): void
    {
        $order = $this->paidOrderWithRefundRequested();
        $this->assertSame('paid', $order->status);
        $this->assertSame(96, $order->total_bdt);
        $this->assertSame(0, (int) $this->reader->fresh()->wallet_balance_bdt);

        $this->billing->refundOrder($order, $this->superAdmin);

        $order = $order->fresh();
        $this->assertSame('refunded', $order->status);
        $this->assertSame('approved', $order->refund_status);

        // Wallet credited by the order total, type=refund, ledger correct.
        $this->assertSame(96, (int) $this->reader->fresh()->wallet_balance_bdt);
        $ledger = WalletTransaction::where('user_id', $this->reader->id)->first();
        $this->assertNotNull($ledger);
        $this->assertSame('refund', $ledger->type);
        $this->assertSame(96, $ledger->amount_bdt);
        $this->assertSame(96, $ledger->balance_after_bdt);
        $this->assertSame('orders', $ledger->reference_type);
        $this->assertSame($order->id, $ledger->reference_id);

        // Audit event, actor = the approving Super Admin.
        $this->assertDatabaseHas('payment_events', [
            'order_id' => $order->id,
            'action' => 'refunded',
            'actor_id' => $this->superAdmin->id,
        ]);

        // Access is NOT revoked on refund (spec silent — open question).
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)->exists());
    }

    public function test_refund_returns_wallet_portion_spent_too(): void
    {
        // Reader pays ৳50 from wallet + ৳46 cash for the ৳96 book.
        $this->reader->update(['wallet_balance_bdt' => 50]);

        $priced = $this->billing->priceOrder([
            'user' => $this->reader->fresh(), 'type' => 'book',
            'book_id' => $this->prophet->id, 'wallet_use_bdt' => 50,
        ]);
        $this->assertSame(50, $priced['wallet_used_bdt']);
        $this->assertSame(46, $priced['total_bdt']);

        $order = $this->billing->createOrder($this->reader->fresh(), $priced);
        $this->assertSame(0, (int) $this->reader->fresh()->wallet_balance_bdt);

        $payment = $this->billing->submitTrxId($order, 'bkash', 'ABC1234567');
        $this->billing->verifyPayment($payment, $this->admin);
        $order->fresh()->update([
            'refund_requested_at' => now(),
            'refund_status' => 'requested',
        ]);

        $this->billing->refundOrder($order->fresh(), $this->superAdmin);

        // Full refund: ৳46 cash + ৳50 wallet portion = ৳96 back.
        $this->assertSame(96, (int) $this->reader->fresh()->wallet_balance_bdt);
        $ledger = WalletTransaction::where('user_id', $this->reader->id)
            ->where('type', 'refund')->first();
        $this->assertNotNull($ledger);
        $this->assertSame(96, $ledger->amount_bdt);
        $this->assertSame(96, $ledger->balance_after_bdt);
    }

    public function test_super_admin_can_approve_refund_under_review(): void
    {
        $order = $this->paidOrderWithRefundRequested();
        $order->update(['refund_status' => 'under_review']);

        $this->billing->refundOrder($order, $this->superAdmin);

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('approved', $order->fresh()->refund_status);
        $this->assertSame(96, (int) $this->reader->fresh()->wallet_balance_bdt);
    }

    public function test_non_super_admin_cannot_approve_refund(): void
    {
        $order = $this->paidOrderWithRefundRequested();

        $this->expectException(AuthorizationException::class);
        $this->billing->refundOrder($order, $this->admin);

        // Nothing changed: order still paid/requested, wallet untouched.
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('requested', $order->fresh()->refund_status);
        $this->assertSame(0, (int) $this->reader->fresh()->wallet_balance_bdt);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('payment_events', ['action' => 'refunded']);
    }

    public function test_reader_cannot_approve_own_refund(): void
    {
        $order = $this->paidOrderWithRefundRequested();

        $this->expectException(AuthorizationException::class);
        $this->billing->refundOrder($order, $this->reader);
    }

    public function test_refund_on_non_paid_order_fails(): void
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
        ]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $this->assertSame('pending_payment', $order->status);
        $order->update(['refund_requested_at' => now(), 'refund_status' => 'requested']);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->refundOrder($order, $this->superAdmin);

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_refund_without_a_request_fails(): void
    {
        $order = $this->paidOrderWithRefundRequested();
        $order->update(['refund_requested_at' => null, 'refund_status' => 'none']);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->refundOrder($order, $this->superAdmin);
    }

    public function test_second_refund_on_same_order_fails(): void
    {
        $order = $this->paidOrderWithRefundRequested();

        $this->billing->refundOrder($order, $this->superAdmin);
        $this->assertSame(96, (int) $this->reader->fresh()->wallet_balance_bdt);

        $this->expectException(InvalidArgumentException::class);
        $this->billing->refundOrder($order->fresh(), $this->superAdmin);

        // No double credit: exactly one refund ledger row, balance unchanged.
        $this->assertSame(1, WalletTransaction::where('user_id', $this->reader->id)
            ->where('type', 'refund')->count());
        $this->assertSame(96, (int) $this->reader->fresh()->wallet_balance_bdt);
    }

    public function test_refund_on_payment_less_zero_total_order_skips_event_row(): void
    {
        // A ৳0 order is paid immediately with no payment row (spec §4.3);
        // payment_events.payment_id is NOT nullable, so no event row is
        // forced — the refund still lands (nothing to credit) and the
        // order is marked refunded/approved.
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
            'wallet_use_bdt' => 0,
        ]);
        $order = (new Order)->forceFill([
            'order_no' => $this->billing->nextOrderNo(),
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'paid',
            'subtotal_bdt' => 0,
            'coupon_amt_bdt' => 0,
            'wallet_used_bdt' => 0,
            'total_bdt' => 0,
            'refund_requested_at' => now(),
            'refund_status' => 'requested',
        ]);
        $order->save();
        unset($priced);

        $this->billing->refundOrder($order, $this->superAdmin);

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('approved', $order->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('payment_events', ['action' => 'refunded']);
    }
}
