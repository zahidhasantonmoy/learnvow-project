<?php

namespace Tests\Feature\Reader;

use App\Models\Download;
use Database\Seeders\BookFileSeeder;

class AudioDownloadTest extends ReaderTestCase
{
    public function test_download_logs_row_and_redirects_to_signed_url(): void
    {
        $this->seed(BookFileSeeder::class);

        $response = $this->actingAs($this->reader)
            ->post('/bn/reader/the-prophet/download');
        $response->assertRedirect();
        $this->assertStringContainsString('/signed-file/epub', $response->headers->get('Location'));

        $download = Download::firstOrFail();
        $this->assertSame($this->reader->id, $download->user_id);
        $this->assertSame($this->prophet->id, $download->book_id);
        $this->assertNotNull($download->file_id);
        $this->assertGreaterThan(0, $download->size_bytes);
        $this->assertNotNull($download->downloaded_at);

        // Downloads screen shows the logged download.
        $this->actingAs($this->reader)->get('/bn/downloads')
            ->assertOk()
            ->assertSee('The Prophet');

        // Owner can delete the log row; another reader cannot.
        $this->actingAs($this->otherReader())
            ->delete("/bn/downloads/{$download->id}")
            ->assertForbidden();
        $this->actingAs($this->reader)
            ->delete("/bn/downloads/{$download->id}")
            ->assertRedirect();
        $this->assertSame(0, Download::count());
    }

    public function test_download_requires_entitlement_and_epub(): void
    {
        $this->actingAs($this->otherReader())
            ->post('/bn/reader/the-prophet/download')
            ->assertForbidden();

        // Entitled but no EPUB file yet -> 404, nothing logged.
        $this->actingAs($this->reader)
            ->post('/bn/reader/the-prophet/download')
            ->assertNotFound();
        $this->assertSame(0, Download::count());
    }

    public function test_audio_file_route_404s_without_audio_row(): void
    {
        $this->actingAs($this->reader)
            ->get('/bn/audio/the-prophet/file')
            ->assertNotFound();
    }
}
