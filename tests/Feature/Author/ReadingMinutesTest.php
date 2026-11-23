<?php

namespace Tests\Feature\Author;

use App\Services\ReaderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reading-minutes ledger (§2.3). The reading_minutes table is Builder B's
 * migration — if it has not landed yet, the service call is a safe no-op
 * and these tests create a TEST-ONLY copy of the §7.4 table to verify the
 * upsert logic itself.
 */
class ReadingMinutesTest extends AuthorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('reading_minutes')) {
            Schema::create('reading_minutes', function ($table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('book_id')->constrained()->cascadeOnDelete();
                $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
                $table->char('period', 7);
                $table->unsignedInteger('minutes')->default(0);
                $table->boolean('counted')->default(false);
                $table->boolean('flagged')->default(false);
                $table->string('flag_note')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'book_id', 'period']);
            });
        }
    }

    public function test_save_progress_records_delta_minutes(): void
    {
        $service = app(ReaderService::class);

        $service->saveProgress($this->reader, $this->prophet, [
            'percent' => 5, 'total_minutes' => 12,
        ]);

        $row = DB::table('reading_minutes')
            ->where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(12, (int) $row->minutes);
        $this->assertSame(now()->format('Y-m'), $row->period);
        $this->assertSame($this->prophet->author_id, (int) $row->author_id);

        // A second save adds only the delta (12 → 20 = +8).
        $service->saveProgress($this->reader, $this->prophet, [
            'percent' => 9, 'total_minutes' => 20,
        ]);

        $this->assertSame(20, (int) DB::table('reading_minutes')
            ->where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)
            ->value('minutes'));

        // No backward movement: total_minutes never shrinks.
        $service->saveProgress($this->reader, $this->prophet, [
            'percent' => 9, 'total_minutes' => 15,
        ]);
        $this->assertSame(20, (int) DB::table('reading_minutes')
            ->where('user_id', $this->reader->id)
            ->where('book_id', $this->prophet->id)
            ->value('minutes'));
    }

    public function test_own_book_minutes_are_never_recorded(): void
    {
        $service = app(ReaderService::class);

        // The demo author reads their own book (Prophet is Gibran's, so
        // use a book owned by the demo author).
        $ownBook = app(\App\Services\AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );

        $service->saveProgress($this->authorUser, $ownBook, [
            'percent' => 50, 'total_minutes' => 30,
        ]);

        $this->assertSame(0, DB::table('reading_minutes')
            ->where('user_id', $this->authorUser->id)
            ->where('book_id', $ownBook->id)
            ->count());
    }

    public function test_record_reading_minutes_is_safe_without_the_table(): void
    {
        // Drop the TEST-ONLY table: the call must not throw.
        Schema::dropIfExists('reading_minutes');

        app(ReaderService::class)->recordReadingMinutes($this->reader, $this->prophet, 0, 10);

        $this->assertFalse(Schema::hasTable('reading_minutes'));
    }
}
