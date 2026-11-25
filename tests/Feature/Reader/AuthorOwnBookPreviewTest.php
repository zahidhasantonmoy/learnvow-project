<?php

namespace Tests\Feature\Reader;

use App\Models\ReadingMinute;
use App\Models\ReadingProgress;
use App\Models\Review;
use App\Models\User;
use App\Services\BookService;

/**
 * Low-2 (role audit 2026-11-25): the author of a book could not open
 * their own book in the reader (403), while another author's book
 * opened fine. The author may now preview their own book — published
 * and in_review — but a preview must never accrue reading minutes
 * toward the subscription pool, and the author still cannot review
 * their own book.
 */
class AuthorOwnBookPreviewTest extends ReaderTestCase
{
    private User $authorUser;

    protected function setUp(): void
    {
        parent::setUp();

        // The Prophet's seeded author (Kahlil Gibran) has user_id NULL;
        // point it at a real author user for these tests.
        $this->authorUser = User::factory()->create(['phone' => '01700000024']);
        $this->prophet->author->update(['user_id' => $this->authorUser->id]);
        $this->prophet->refresh();
    }

    public function test_author_opens_own_published_book_in_reader(): void
    {
        $this->actingAs($this->authorUser)
            ->get('/en/reader/the-prophet')
            ->assertOk();
    }

    public function test_author_opens_own_in_review_book_in_reader(): void
    {
        $this->prophet->update(['status' => 'in_review']);

        $this->actingAs($this->authorUser)
            ->get('/en/reader/the-prophet')
            ->assertOk();
    }

    public function test_non_author_without_purchase_still_gets_403(): void
    {
        $this->actingAs($this->otherReader())
            ->get('/en/reader/the-prophet')
            ->assertForbidden();
    }

    public function test_can_read_allows_author_but_not_unentitled_reader(): void
    {
        $books = app(BookService::class);

        $this->assertTrue($books->canRead($this->authorUser, $this->prophet));
        $this->assertFalse($books->canRead($this->otherReader(), $this->prophet));
        $this->assertTrue($books->isOwnBook($this->authorUser, $this->prophet));
        $this->assertFalse($books->isOwnBook($this->reader, $this->prophet));
    }

    /**
     * Preview progress (the real reader.js PUT path): the reading
     * position is still saved, but NO row reaches the reading_minutes
     * ledger that the pool is split from.
     */
    public function test_author_preview_does_not_accrue_reading_minutes(): void
    {
        $response = $this->actingAs($this->authorUser)->putJson(
            '/bn/reader/the-prophet/progress',
            ['cfi' => 'epubcfi(/6/2[chapter-1]!/4/2)', 'percent' => 50, 'chapter_index' => 1, 'total_minutes' => 12, 'last_read_at' => now()->toISOString()]
        );
        $response->assertOk()->assertJson(['percent' => 50, 'total_minutes' => 12]);

        $progress = ReadingProgress::where('user_id', $this->authorUser->id)
            ->where('book_id', $this->prophet->id)->firstOrFail();
        $this->assertSame(12, $progress->total_minutes);

        $this->assertSame(0, ReadingMinute::where('user_id', $this->authorUser->id)
            ->where('book_id', $this->prophet->id)->count());
        $this->assertSame(0, (int) ReadingMinute::where('user_id', $this->authorUser->id)
            ->where('book_id', $this->prophet->id)->sum('minutes'));
    }

    /** Control: the same progress save from an entitled reader DOES accrue minutes. */
    public function test_entitled_reader_progress_still_accrues_reading_minutes(): void
    {
        $this->actingAs($this->reader)->putJson(
            '/bn/reader/the-prophet/progress',
            ['percent' => 50, 'total_minutes' => 12, 'last_read_at' => now()->toISOString()]
        )->assertOk();

        $this->assertSame(12, (int) ReadingMinute::where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)->sum('minutes'));
    }

    /** Preview is not a review entitlement: the author cannot review their own book. */
    public function test_author_cannot_review_own_book(): void
    {
        $this->actingAs($this->authorUser)->post('/bn/books/the-prophet/review', [
            'rating' => 5, 'title' => 'My own book', 'body' => 'A glowing self-review that must never land.',
        ])->assertSessionHasErrors('review');

        $this->assertSame(0, Review::where('user_id', $this->authorUser->id)
            ->where('book_id', $this->prophet->id)->count());
    }
}
