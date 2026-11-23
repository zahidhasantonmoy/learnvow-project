<?php

namespace Tests\Feature\Author;

use App\Models\Book;
use App\Models\BookAppeal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserBook;
use App\Services\AuthorService;
use App\Services\BookService;

class PricePauseAppealTest extends AuthorTestCase
{
    protected Book $ownBook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownBook = app(AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );
        app(\App\Services\AdminService::class)->approveBook($this->ownBook);
        $this->ownBook = $this->ownBook->fresh();
    }

    public function test_price_change_keeps_old_order_snapshot(): void
    {
        // An earlier paid order for this book at ৳150.
        $order = Order::create([
            'order_no' => 'LV-2026-9001', 'user_id' => $this->reader->id,
            'type' => 'book', 'status' => 'paid', 'subtotal_bdt' => 150, 'total_bdt' => 150,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'book_id' => $this->ownBook->id,
            'title_snapshot' => $this->ownBook->title, 'unit_price_bdt' => 150,
            'list_price_bdt' => 200, 'quantity' => 1,
        ]);

        app(AuthorService::class)->updatePrice($this->author, $this->ownBook, [
            'price_bdt' => 99, 'list_price_bdt' => 120,
        ]);

        $this->assertSame(99, $this->ownBook->fresh()->price_bdt);
        // The snapshot is untouched.
        $this->assertSame(150, OrderItem::first()->unit_price_bdt);
        $this->assertSame(200, OrderItem::first()->list_price_bdt);
    }

    public function test_price_change_requires_own_book(): void
    {
        $otherAuthor = \App\Models\Author::where('slug', 'kahlil-gibran')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->updatePrice($otherAuthor, $this->ownBook, ['price_bdt' => 1]);
    }

    public function test_pause_hides_from_saleable_query_but_buyer_keeps_access(): void
    {
        // Old buyer entitlement (Phase 2).
        UserBook::create([
            'user_id' => $this->reader->id, 'book_id' => $this->ownBook->id, 'source' => 'purchase',
        ]);

        app(AuthorService::class)->pauseBook($this->author, $this->ownBook);
        $this->assertTrue($this->ownBook->fresh()->is_paused);

        // Not saleable any more.
        $this->assertFalse(
            app(AuthorService::class)->saleableBooksQuery()->where('books.id', $this->ownBook->id)->exists()
        );

        // But the old buyer can still read (canRead unchanged).
        $this->assertTrue(app(BookService::class)->canRead($this->reader, $this->ownBook->fresh()));

        app(AuthorService::class)->resumeBook($this->author, $this->ownBook);
        $this->assertFalse($this->ownBook->fresh()->is_paused);
        $this->assertTrue(
            app(AuthorService::class)->saleableBooksQuery()->where('books.id', $this->ownBook->id)->exists()
        );
    }

    public function test_pause_resume_via_http(): void
    {
        $this->actingAuthor()
            ->post("/bn/author/books/{$this->ownBook->slug}/pause")
            ->assertRedirect();

        $this->assertTrue($this->ownBook->fresh()->is_paused);

        $this->actingAuthor()
            ->post("/bn/author/books/{$this->ownBook->slug}/resume")
            ->assertRedirect();

        $this->assertFalse($this->ownBook->fresh()->is_paused);
    }

    public function test_other_author_cannot_pause_my_book(): void
    {
        $other = \App\Models\User::factory()->create();
        $other->assignRole('reader');

        $this->actingAs($other)
            ->post("/bn/author/books/{$this->ownBook->slug}/pause")
            ->assertForbidden();
    }

    public function test_rejected_book_appeal_returns_it_to_in_review(): void
    {
        app(\App\Services\AdminService::class)->rejectBook($this->ownBook, 'Cover too low resolution.');
        $this->assertSame('rejected', $this->ownBook->fresh()->status);

        $appeal = app(AuthorService::class)->fileAppeal($this->author, $this->ownBook, 'New cover uploaded.');

        $this->assertSame('open', $appeal->status);
        $this->assertSame(1, BookAppeal::where('book_id', $this->ownBook->id)->count());
        $this->assertSame('in_review', $this->ownBook->fresh()->status);
    }

    public function test_appeal_of_non_rejected_book_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->fileAppeal($this->author, $this->ownBook, 'Please re-check.');
    }

    public function test_author_cannot_buy_own_book(): void
    {
        // Phase 2 checkout guard (roles doc edge case 4) — assert it.
        $this->actingAuthor()
            ->get("/bn/checkout/{$this->ownBook->slug}")
            ->assertRedirect('/bn/books/'.$this->ownBook->slug);
    }

    public function test_my_books_page_lists_own_books_only(): void
    {
        $this->actingAuthor()
            ->get('/bn/author/books')
            ->assertOk()
            ->assertSee($this->ownBook->title)
            ->assertDontSee('Gitanjali');
    }
}
