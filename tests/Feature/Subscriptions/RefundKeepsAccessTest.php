<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\UserBook;
use App\Services\AdminService;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * K2 REGRESSION TEST ONLY (Phase 4 spec §18, decision 5): a refund
 * reverses the author's earnings but the buyer KEEPS access. No refund
 * code is changed here — this test locks the Phase 2/3 behaviour.
 */
class RefundKeepsAccessTest extends TestCase
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

        \DB::table('settings')->insert([['key' => 'points.per_purchase', 'value' => '10']]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        AdminService::assignRole($this->reader, 'reader');
        $this->admin = User::factory()->create(['phone' => '01700000001']);
        AdminService::assignRole($this->admin, 'admin');
        $this->superAdmin = User::factory()->create(['phone' => '01700000003']);
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->billing = app(BillingService::class);
    }

    public function test_refund_reverses_author_earnings_but_buyer_keeps_access(): void
    {
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', 'ABC1234567');
        $this->billing->verifyPayment($payment, $this->admin);

        // Paid: author earned 80% of ৳96 = ৳77; buyer has the book.
        $this->assertSame(77, (int) AuthorEarning::where('order_id', $order->id)->sum('amount_bdt'));
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());

        $order->fresh()->update(['refund_requested_at' => now(), 'refund_status' => 'requested']);
        $this->billing->refundOrder($order->fresh(), $this->superAdmin);

        // Earnings reversed to zero net…
        $this->assertSame(0, (int) AuthorEarning::where('order_id', $order->id)->sum('amount_bdt'));
        $this->assertDatabaseHas('author_earnings', [
            'order_id' => $order->id, 'type' => 'adjustment',
        ]);
        // …but the buyer's access is untouched (K2 / decision 5).
        $this->assertTrue(UserBook::where('user_id', $this->reader->id)->where('book_id', $this->prophet->id)->exists());
        $this->assertSame('refunded', $order->fresh()->status);
    }
}
