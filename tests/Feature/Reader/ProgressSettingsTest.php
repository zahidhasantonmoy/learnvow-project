<?php

namespace Tests\Feature\Reader;

use App\Models\ReadingProgress;
use App\Models\ReaderSetting;
use App\Services\ReaderService;
use Livewire\Livewire;

class ProgressSettingsTest extends ReaderTestCase
{
    public function test_progress_saves_and_finishing_at_100_sets_finished_at(): void
    {
        $response = $this->actingAs($this->reader)->putJson(
            '/bn/reader/the-prophet/progress',
            ['cfi' => 'epubcfi(/6/2[chapter-1]!/4/2)', 'percent' => 42, 'chapter_index' => 1, 'total_minutes' => 12, 'last_read_at' => now()->toISOString()]
        );
        $response->assertOk()->assertJson(['percent' => 42, 'total_minutes' => 12, 'finished' => false]);

        $progress = ReadingProgress::where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)->firstOrFail();
        $this->assertSame(42, $progress->percent);
        $this->assertNull($progress->finished_at);

        $this->actingAs($this->reader)->putJson(
            '/bn/reader/the-prophet/progress',
            ['percent' => 100, 'total_minutes' => 55, 'last_read_at' => now()->addMinute()->toISOString()]
        )->assertOk()->assertJson(['finished' => true]);

        $progress->refresh();
        $this->assertNotNull($progress->finished_at);

        // Finished screen now renders (X71) with the finished badge (X82).
        $this->actingAs($this->reader)->get('/bn/reader/the-prophet/finished')
            ->assertOk()
            ->assertSee(__('reader.finished_heading'));
    }

    public function test_stale_progress_save_is_ignored_server_wins_by_last_read_at(): void
    {
        $service = app(ReaderService::class);
        $service->saveProgress($this->reader, $this->prophet, [
            'percent' => 60, 'last_read_at' => now()->subHours(2)->toISOString(),
        ]);

        // An older save must not rewind the stored row.
        $kept = $service->saveProgress($this->reader, $this->prophet, [
            'percent' => 10, 'last_read_at' => now()->subHours(3)->toISOString(),
        ]);
        $this->assertSame(60, $kept->percent);

        // total_minutes only ever grows.
        $service->saveProgress($this->reader, $this->prophet, [
            'total_minutes' => 5, 'last_read_at' => now()->subHour()->toISOString(),
        ]);
        $service->saveProgress($this->reader, $this->prophet, [
            'total_minutes' => 3, 'last_read_at' => now()->toISOString(),
        ]);
        $this->assertSame(5, $service->progressFor($this->reader, $this->prophet)->total_minutes);
    }

    public function test_progress_requires_entitlement(): void
    {
        $this->actingAs($this->otherReader())->putJson(
            '/bn/reader/the-prophet/progress',
            ['percent' => 10]
        )->assertForbidden();
    }

    public function test_pool_qualification_is_10_minutes_or_10_percent(): void
    {
        $service = app(ReaderService::class);
        $progress = ReadingProgress::create([
            'user_id' => $this->reader->id, 'book_id' => $this->prophet->id,
            'percent' => 9, 'total_minutes' => 9,
        ]);
        $this->assertFalse($service->qualifiesForPool($progress));

        $progress->update(['total_minutes' => 10]);
        $this->assertTrue($service->qualifiesForPool($progress));

        $progress->update(['total_minutes' => 0, 'percent' => 10]);
        $this->assertTrue($service->qualifiesForPool($progress));
    }

    public function test_per_book_settings_do_not_leak_to_other_books(): void
    {
        Livewire::actingAs($this->reader)
            ->test('reading-settings', ['book' => $this->prophet])
            ->set('fontSize', 150)
            ->set('theme', 'night')
            ->assertSet('fontSize', 150);

        $prophetSettings = ReaderSetting::where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)->firstOrFail();
        $this->assertSame(150, $prophetSettings->font_size);
        $this->assertSame('night', $prophetSettings->theme);

        // The other book still falls back to the system default.
        $gitanjaliSettings = app(ReaderService::class)->settingsFor($this->reader, $this->gitanjali);
        $this->assertSame(100, $gitanjaliSettings->font_size);
        $this->assertSame('paper', $gitanjaliSettings->theme);
        $this->assertFalse($gitanjaliSettings->exists);

        // Settings page renders for the entitled book in both locales.
        $this->actingAs($this->reader)->get('/bn/reader/the-prophet/settings')->assertOk();
        $this->actingAs($this->reader)->get('/en/reader/the-prophet/settings')->assertOk();
    }

    public function test_reader_view_livewire_saves_progress(): void
    {
        Livewire::actingAs($this->reader)
            ->test('reader-view', ['book' => $this->prophet])
            ->call('saveProgress', 'epubcfi(/6/2)', 25, 0, 4)
            ->assertSet('percent', 25);

        $this->assertSame(25, app(ReaderService::class)
            ->progressFor($this->reader, $this->prophet)->percent);
    }

    public function test_audio_player_cycles_speed_in_prototype_order(): void
    {
        Livewire::actingAs($this->reader)
            ->test('audio-player', ['book' => $this->prophet])
            ->call('cycleSpeed')->assertSet('speed', 1.25)
            ->call('cycleSpeed')->assertSet('speed', 1.5)
            ->call('cycleSpeed')->assertSet('speed', 2.0)
            ->call('cycleSpeed')->assertSet('speed', 0.75)
            ->call('cycleSpeed')->assertSet('speed', 1.0);
    }
}
