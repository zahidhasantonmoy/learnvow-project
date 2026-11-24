<?php

namespace Tests\Feature\Reader;

use App\Models\Book;
use App\Models\BookRequest;
use App\Models\Preorder;
use Illuminate\Support\Facades\Schema;

class PreorderRequestTest extends ReaderTestCase
{
    protected Book $upcoming;

    protected function setUp(): void
    {
        parent::setUp();

        // Builder C's §12.2 books columns (is_preorder, preorder_release_at)
        // have not landed yet — add them here when missing so the
        // preorder rules can be tested against the spec schema.
        if (! Schema::hasColumn('books', 'is_preorder')) {
            Schema::table('books', function ($table) {
                $table->boolean('is_preorder')->default(false);
                $table->timestamp('preorder_release_at')->nullable();
                $table->unsignedInteger('licensed_pdf_price_bdt')->nullable();
            });
        }

        $this->upcoming = Book::create([
            'author_id' => $this->prophet->author_id,
            'category_id' => $this->prophet->category_id,
            'title' => 'Coming Soon',
            'slug' => 'coming-soon',
            'description' => 'Not published yet.',
            'language' => 'en',
            'price_bdt' => 150,
            'status' => 'in_review',
            'is_preorder' => true,
            'preorder_release_at' => now()->addMonth(),
        ]);
    }

    public function test_preorder_button_renders_for_seeded_preorderable_book(): void
    {
        // Regression (QA M3): the PreorderButton component existed but
        // was included in zero views, and no seeded book was preorderable.
        // Meditations is seeded preorderable (BookSeeder, not Prophet).
        $meditations = Book::where('slug', 'meditations')->firstOrFail();
        $this->assertTrue($meditations->is_preorder);
        $this->assertTrue($meditations->preorder_release_at->isFuture());
        $this->assertTrue(app(\App\Http\Controllers\PreorderController::class)->isPreorderable($meditations));

        $this->actingAs($this->reader);
        $this->get('/bn/books/meditations')
            ->assertOk()
            ->assertSee('প্রি-অর্ডার করুন')
            ->assertSee('/bn/books/meditations/print-order', false);

        // Prophet is not preorderable: no preorder button, but the
        // print-order link is there (allow_pod defaults true).
        $this->get('/bn/books/the-prophet')
            ->assertOk()
            ->assertDontSee('প্রি-অর্ডার করুন')
            ->assertSee('/bn/books/the-prophet/print-order', false);
    }

    public function test_preorder_reserve_and_cancel(): void
    {
        $this->actingAs($this->reader);

        $this->post("/bn/books/{$this->upcoming->slug}/preorder")->assertRedirect();
        $this->assertDatabaseHas('preorders', [
            'user_id' => $this->reader->id, 'book_id' => $this->upcoming->id, 'status' => 'reserved',
        ]);

        // One active preorder per user+book.
        $this->post("/bn/books/{$this->upcoming->slug}/preorder")->assertSessionHasErrors('book');

        $this->get('/bn/preorders')->assertOk()->assertSee('Coming Soon');

        $preorder = Preorder::where('user_id', $this->reader->id)->firstOrFail();
        $this->delete("/bn/preorders/{$preorder->id}")->assertRedirect();
        $this->assertEquals('cancelled', $preorder->fresh()->status);

        // After cancelling, reserving again works.
        $this->post("/bn/books/{$this->upcoming->slug}/preorder")->assertRedirect();
        $this->assertEquals(1, Preorder::where('user_id', $this->reader->id)
            ->where('status', 'reserved')->count());
    }

    public function test_preorder_rejected_for_published_book(): void
    {
        $this->actingAs($this->reader);
        $this->post("/bn/books/{$this->prophet->slug}/preorder")->assertSessionHasErrors('book');
    }

    public function test_preorder_button_livewire(): void
    {
        $this->actingAs($this->reader);
        \Livewire\Livewire::test(\App\Livewire\PreorderButton::class, ['book' => $this->upcoming])
            ->call('reserve')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('preorders', [
            'user_id' => $this->reader->id, 'book_id' => $this->upcoming->id, 'status' => 'reserved',
        ]);
    }

    public function test_book_request_and_monthly_limit_five(): void
    {
        $this->actingAs($this->reader);

        $this->post('/bn/request-book', ['title' => 'Dune', 'author_name' => 'Frank Herbert'])
            ->assertRedirect();
        $this->assertDatabaseHas('book_requests', ['user_id' => $this->reader->id, 'title' => 'Dune']);

        // Same title counted across users.
        $other = $this->otherReader();
        BookRequest::create(['user_id' => $other->id, 'title' => 'Dune']);
        $this->get('/bn/request-book')->assertOk()->assertSee('Dune');

        // Fill the month to 5, the 6th is rejected.
        for ($i = 2; $i <= 5; $i++) {
            BookRequest::create(['user_id' => $this->reader->id, 'title' => 'Book '.$i]);
        }
        $this->post('/bn/request-book', ['title' => 'Sixth'])->assertSessionHasErrors('title');
        $this->assertEquals(5, BookRequest::where('user_id', $this->reader->id)->count());
    }

    public function test_request_book_form_livewire(): void
    {
        $this->actingAs($this->reader);
        \Livewire\Livewire::test(\App\Livewire\RequestBookForm::class)
            ->set('title', 'Foundation')
            ->set('authorName', 'Isaac Asimov')
            ->call('submit')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('book_requests', ['user_id' => $this->reader->id, 'title' => 'Foundation']);
    }
}
