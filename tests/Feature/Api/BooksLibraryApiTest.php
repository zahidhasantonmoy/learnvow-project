<?php

namespace Tests\Feature\Api;

use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\UserBook;
use App\Services\BookFileService;

class BooksLibraryApiTest extends ApiTestCase
{
    public function test_books_index_lists_published_books_with_can_read(): void
    {
        $this->asReader();

        $response = $this->getJson('/api/v1/books');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
        $this->assertContains('the-prophet', collect($response->json('data'))->pluck('slug')->all());
    }

    public function test_books_show_and_search(): void
    {
        $this->asReader();
        $this->getJson('/api/v1/books/the-prophet')->assertOk()
            ->assertJsonPath('data.slug', 'the-prophet')
            ->assertJsonPath('data.can_read', false)
            ->assertJsonPath('data.price_bdt', 96);

        $this->getJson('/api/v1/search?q=prophet')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'the-prophet');

        $this->getJson('/api/v1/books/does-not-exist')->assertNotFound();
    }

    public function test_library_lists_owned_books_only(): void
    {
        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase']);
        $this->asReader();

        $this->getJson('/api/v1/library')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'the-prophet')
            ->assertJsonPath('data.0.can_read', true);
    }

    public function test_progress_save_and_read(): void
    {
        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase']);
        $this->asReader();

        $this->putJson('/api/v1/reader/the-prophet/progress', [
            'percent' => 42, 'total_minutes' => 15, 'chapter_index' => 3,
        ])->assertOk()
            ->assertJsonPath('data.percent', 42)
            ->assertJsonPath('data.total_minutes', 15);

        $this->getJson('/api/v1/reader/the-prophet/progress')->assertOk()
            ->assertJsonPath('data.percent', 42);
    }

    public function test_progress_requires_access(): void
    {
        $this->asReader();
        $this->putJson('/api/v1/reader/the-prophet/progress', ['percent' => 10])
            ->assertForbidden();
    }

    public function test_file_returns_signed_url_for_owned_book(): void
    {
        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase']);
        app(BookFileService::class)->storeDemoEpub($this->prophet, 'fake-epub-bytes');
        $this->asReader();

        $this->getJson('/api/v1/reader/the-prophet/file')->assertOk()
            ->assertJsonStructure(['data' => ['url', 'expires_in_minutes']]);
    }

    public function test_bookmarks_highlights_notes_crud(): void
    {
        $this->asReader();

        $bookmark = $this->postJson('/api/v1/reader/the-prophet/bookmarks', ['cfi' => 'epubcfi(/6/2)', 'label' => 'Fav'])
            ->assertCreated()->assertJsonPath('data.cfi', 'epubcfi(/6/2)');
        $this->getJson('/api/v1/reader/the-prophet/bookmarks')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/reader/the-prophet/bookmarks/'.$bookmark->json('data.id'))->assertOk();
        $this->assertDatabaseCount('bookmarks', 0);

        $highlight = $this->postJson('/api/v1/reader/the-prophet/highlights', [
            'cfi_start' => 'epubcfi(/6/2)', 'cfi_end' => 'epubcfi(/6/4)', 'color' => 'green',
        ])->assertCreated()->assertJsonPath('data.color', 'green');
        $this->getJson('/api/v1/reader/the-prophet/highlights')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/reader/the-prophet/highlights/'.$highlight->json('data.id'))->assertOk();
        $this->assertDatabaseCount('highlights', 0);

        $note = $this->postJson('/api/v1/reader/the-prophet/notes', ['cfi' => 'epubcfi(/6/2)', 'body' => 'Remember this.'])
            ->assertCreated()->assertJsonPath('data.body', 'Remember this.');
        $this->getJson('/api/v1/reader/the-prophet/notes')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/v1/reader/the-prophet/notes/'.$note->json('data.id'))->assertOk();
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_other_users_bookmarks_cannot_be_deleted(): void
    {
        $other = \App\Models\User::factory()->create();
        $bookmark = Bookmark::create(['user_id' => $other->id, 'book_id' => $this->prophet->id, 'cfi' => 'epubcfi(/6/2)']);
        $this->asReader();

        $this->deleteJson('/api/v1/reader/the-prophet/bookmarks/'.$bookmark->id)->assertForbidden();
        $this->assertDatabaseHas('bookmarks', ['id' => $bookmark->id]);
    }
}
