<?php

namespace Tests\Feature\Earnings;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\Book;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coordinator integration test (Phase 3 §2.1): a book bought entirely with
 * wallet balance is a ৳0 order paid inside BillingService::createOrder() —
 * it never passes through verifyPayment(), so the author's 80% must accrue
 * there. Wallet money is real money.
 */
class WalletAccrualTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_covered_order_accrues_author_earnings(): void
    {
        foreach (['reader', 'author', 'admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);
        $authorUser = User::factory()->create(['phone' => '01700000003']);
        $authorUser->assignRole('author');
        $author = Author::create([
            'name' => 'Kahlil Gibran', 'slug' => 'kahlil-gibran',
            'user_id' => $authorUser->id, 'status' => 'approved',
        ]);
        $prophet = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);

        $reader = User::factory()->create(['phone' => '01700000002']);
        $reader->assignRole('reader');

        $billing = app(BillingService::class);
        $billing->walletTopup($reader->fresh(), 200, 'test', null, 'Test top-up');

        $priced = $billing->priceOrder([
            'user' => $reader->fresh(), 'type' => 'book', 'book_id' => $prophet->id,
            'wallet_use_bdt' => 96,
        ]);

        $this->assertSame(0, $priced['total_bdt']);
        $this->assertSame(96, $priced['wallet_used_bdt']);

        $order = $billing->createOrder($reader->fresh(), $priced);

        $this->assertSame('paid', $order->fresh()->status);

        // Prophet ৳96 sale → author 80% = ৳77, accrued without verifyPayment.
        $earning = AuthorEarning::where('order_id', $order->id)
            ->where('book_id', $prophet->id)
            ->where('type', AuthorEarning::TYPE_SALE)
            ->first();

        $this->assertNotNull($earning);
        $this->assertSame(77, (int) $earning->amount_bdt);
        $this->assertSame($author->id, (int) $earning->author_id);
    }

    public function test_paused_and_hidden_books_leave_public_listing(): void
    {
        foreach (['reader', 'author'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);
        $authorUser = User::factory()->create(['phone' => '01700000003']);
        $author = Author::create([
            'name' => 'Kahlil Gibran', 'slug' => 'kahlil-gibran',
            'user_id' => $authorUser->id, 'status' => 'approved',
        ]);

        $live = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'Live Book', 'slug' => 'live-book', 'description' => 'Live.',
            'language' => 'en', 'price_bdt' => 100, 'status' => 'published',
        ]);
        $paused = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'Paused Book', 'slug' => 'paused-book', 'description' => 'Paused.',
            'language' => 'en', 'price_bdt' => 100, 'status' => 'published', 'is_paused' => true,
        ]);
        $hidden = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'Hidden Book', 'slug' => 'hidden-book', 'description' => 'Hidden.',
            'language' => 'en', 'price_bdt' => 100, 'status' => 'published',
            'hidden_at' => now(),
        ]);

        $service = app(\App\Services\BookService::class);

        $listed = $service->list(['per_page' => 10])->items();
        $slugs = collect($listed)->pluck('slug')->all();
        $this->assertContains('live-book', $slugs);
        $this->assertNotContains('paused-book', $slugs);
        $this->assertNotContains('hidden-book', $slugs);

        $this->assertSame('live-book', $service->show('live-book')->slug);

        foreach (['paused-book', 'hidden-book'] as $slug) {
            try {
                $service->show($slug);
                $this->fail("Book page for {$slug} should 404.");
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(1, Book::published()->count());
    }
}
