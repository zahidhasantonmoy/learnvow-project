<?php

namespace Tests\Feature\Reader;

use App\Models\BookFile;
use App\Services\BookFileService;
use Database\Seeders\BookFileSeeder;
use Illuminate\Support\Facades\Storage;

class ReaderTest extends ReaderTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/bn/reader/the-prophet')->assertRedirectContains('/bn/login');
        $this->get('/bn/bookmarks')->assertRedirectContains('/bn/login');
        $this->get('/bn/audio/the-prophet')->assertRedirectContains('/bn/login');
        $this->get('/bn/listen/the-prophet')->assertRedirectContains('/bn/login');
        $this->get('/bn/downloads')->assertRedirectContains('/bn/login');
    }

    public function test_entitled_reader_opens_free_and_bought_books_with_noindex(): void
    {
        foreach (['gitanjali', 'the-prophet'] as $slug) {
            $response = $this->actingAs($this->reader)->get("/bn/reader/{$slug}");
            $response->assertOk();
            $response->assertSee('noindex, nofollow', false);
        }
    }

    public function test_reader_without_entitlement_gets_403_on_paid_book(): void
    {
        $this->actingAs($this->otherReader())
            ->get('/bn/reader/the-prophet')
            ->assertForbidden();
    }

    public function test_reader_page_uses_file_route_not_signed_url_or_raw_path(): void
    {
        $this->seed(BookFileSeeder::class);

        $response = $this->actingAs($this->reader)->get('/bn/reader/the-prophet');
        $response->assertOk();
        $response->assertSee('/bn/reader/the-prophet/file', false);
        $response->assertDontSee('signed-file');
        $response->assertDontSee('bookfiles');
    }

    public function test_file_route_redirects_to_signed_url_and_signed_route_streams_epub(): void
    {
        $this->seed(BookFileSeeder::class);

        $response = $this->actingAs($this->reader)
            ->get('/bn/reader/the-prophet/file');
        $response->assertRedirect();
        $this->assertStringContainsString('/signed-file/epub', $response->headers->get('Location'));

        $signed = app(BookFileService::class)->signedFileUrl($this->prophet, 'epub');
        $streamed = $this->actingAs($this->reader)->get($signed);
        $streamed->assertOk();
        $this->assertSame('application/epub+zip', $streamed->headers->get('Content-Type'));

        // The stored EPUB bytes are a real zip whose first entry is the
        // stored mimetype. (StreamedResponse content is not buffered in
        // tests, so the bytes are checked from storage, not getContent().)
        $file = BookFile::where('book_id', $this->prophet->id)->where('type', 'epub')->firstOrFail();
        $bytes = Storage::disk($file->disk)->get($file->path);
        $this->assertSame('PK', substr($bytes, 0, 2));
        $tmp = tempnam(sys_get_temp_dir(), 'lv-test-epub-');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $this->assertSame(true, $zip->open($tmp));
        $this->assertSame('mimetype', $zip->getNameIndex(0));
        $this->assertSame('application/epub+zip', $zip->getFromIndex(0));
        $this->assertStringContainsString('Demo content', $zip->getFromName('OEBPS/chapter-1.xhtml'));
        $zip->close();
        unlink($tmp);
    }

    public function test_signed_file_url_rejects_guest_and_unentitled_reader(): void
    {
        $this->seed(BookFileSeeder::class);
        $signed = app(BookFileService::class)->signedFileUrl($this->prophet, 'epub');

        $this->get($signed)->assertRedirectContains('/bn/login');
        $this->actingAs($this->otherReader())->get($signed)->assertForbidden();
    }

    public function test_file_route_404s_when_no_epub_exists(): void
    {
        $this->actingAs($this->reader)
            ->get('/bn/reader/the-prophet/file')
            ->assertNotFound();
    }

    public function test_bookmarks_audio_tts_and_downloads_pages_render(): void
    {
        $this->actingAs($this->reader)->get('/bn/bookmarks')->assertOk();
        $this->actingAs($this->reader)->get('/bn/downloads')->assertOk();

        // No seed audio files: Prophet (has_audiobook) shows "coming soon" + TTS.
        $audio = $this->actingAs($this->reader)->get('/bn/audio/the-prophet');
        $audio->assertOk();
        $audio->assertSee(__('reader.audio_coming_soon'));
        $audio->assertSee(__('reader.listen_tts'));

        $tts = $this->actingAs($this->reader)->get('/bn/listen/the-prophet');
        $tts->assertOk();
        $tts->assertSee(__('reader.tts_heading'));

        // Recorded-audiobook badge rule (v3.21): no audio file -> TTS badge on reader page.
        $this->seed(BookFileSeeder::class);
        $this->actingAs($this->reader)->get('/bn/reader/the-prophet')
            ->assertSee(__('reader.tts_badge'));
    }

    public function test_finished_page_redirects_until_book_is_finished(): void
    {
        $this->actingAs($this->reader)
            ->get('/bn/reader/the-prophet/finished')
            ->assertRedirectContains('/bn/reader/the-prophet');
    }

    public function test_seeder_creates_a_valid_demo_epub_for_every_seeded_book(): void
    {
        $this->seed(BookFileSeeder::class);

        $this->assertSame(8, BookFile::where('type', 'epub')->count());

        foreach (BookFile::where('type', 'epub')->get() as $file) {
            $this->assertTrue(Storage::disk($file->disk)->exists($file->path));
            $this->assertSame('application/epub+zip', $file->mime);
            $this->assertGreaterThan(0, $file->size_bytes);
        }
    }
}
