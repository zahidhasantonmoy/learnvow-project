<?php

namespace Tests\Feature\Author;

use App\Models\Book;
use App\Models\BookFile;
use App\Services\AuthorService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

class PublishBookTest extends AuthorTestCase
{
    public function test_publish_form_states_80_percent_share(): void
    {
        $this->actingAuthor()
            ->get('/bn/author/publish')
            ->assertOk()
            ->assertSee(__('author.publish_share_note'));
    }

    public function test_pending_author_cannot_open_publish_form(): void
    {
        // Fix round 1 (minor): no bare 403 any more. A Reader who never
        // applied is redirected to apply; a pending applicant sees the
        // friendly waiting message (no form).
        $this->actingReader()->get('/bn/author/publish')->assertRedirectContains('/bn/author/apply');

        \App\Models\AuthorApplication::create([
            'user_id' => $this->reader->id,
            'author_id' => $this->author->id,
            'bkash_number' => '01700000002',
            'status' => 'submitted',
        ]);

        $this->actingReader()->get('/bn/author/publish')
            ->assertOk()
            ->assertSee(__('author.apply_waiting_approval'))
            ->assertDontSee(__('author.publish_submit'));
    }

    public function test_valid_epub_publishes_to_in_review(): void
    {
        $this->actingAuthor();

        Livewire::test('author-upload')
            ->set('title', 'Test Book Title')
            ->set('description', 'A test book description.')
            ->set('language', 'en')
            ->set('categoryId', $this->publishData()['category_id'])
            ->set('priceBdt', 150)
            ->set('listPriceBdt', 200)
            ->set('bookFile', $this->validEpub())
            ->set('cover', UploadedFile::fake()->image('cover.png', 1600, 2400))
            ->call('submit');

        $book = Book::where('slug', 'test-book-title')->firstOrFail();
        $this->assertSame('in_review', $book->status); // never published directly
        $this->assertSame(150, $book->price_bdt);
        $this->assertSame(200, $book->list_price_bdt);
        $this->assertSame($this->author->id, $book->author_id);
        $this->assertNotNull($book->cover_image_path);

        $file = BookFile::where('book_id', $book->id)->where('type', 'epub')->firstOrFail();
        $this->assertSame('application/epub+zip', $file->mime);
    }

    public function test_broken_epub_is_rejected(): void
    {
        $service = app(AuthorService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/EPUB/');

        $service->publishBook($this->author, $this->publishData(), $this->brokenEpub());
    }

    public function test_cover_smaller_than_1600x2400_is_rejected(): void
    {
        $service = app(AuthorService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/1600/');

        $service->publishBook(
            $this->author,
            $this->publishData(),
            $this->validEpub(),
            UploadedFile::fake()->image('small.png', 800, 600)
        );
    }

    public function test_publish_page_works_in_english_too(): void
    {
        $this->actingAuthor()
            ->get('/en/author/publish')
            ->assertOk()
            ->assertSee('Publish a new book');
    }

    public function test_admin_approval_publishes_the_book(): void
    {
        $book = app(AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );
        $this->assertSame('in_review', $book->status);

        app(\App\Services\AdminService::class)->approveBook($book);

        $this->assertSame('published', $book->fresh()->status);
        $this->assertNotNull($book->fresh()->published_at);
    }

    public function test_admin_rejection_returns_book_with_reason_logged(): void
    {
        $book = app(AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );

        app(\App\Services\AdminService::class)->rejectBook($book, 'Cover too low resolution.');

        $this->assertSame('rejected', $book->fresh()->status);
    }
}
