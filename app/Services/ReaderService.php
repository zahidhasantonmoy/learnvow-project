<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\ReaderSetting;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reader rules: per-book settings, progress, bookmarks / highlights /
 * notes. Spec §1, §11 — Builder A owns the methods inside this file
 * (the coordinator only created the file).
 *
 * Fallback order for settings (§1.2): this book's row → the user's
 * default (users.reader_default_font / reader_default_size, added by
 * Builder B's §6.22 migration — guarded with hasColumn until it lands)
 * → system default (serif, 100%, line-height 1.60, paper, 100%, paged).
 */
class ReaderService
{
    public const SYSTEM_DEFAULTS = [
        'font_family' => 'serif',
        'font_size' => 100,
        'line_height' => 1.60,
        'theme' => 'paper',
        'brightness' => 100,
        'reading_mode' => 'paged',
    ];

    /** Per-book settings, falling back to user default then system default. */
    public function settingsFor(User $user, Book $book): ReaderSetting
    {
        $row = ReaderSetting::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->first();

        if ($row !== null) {
            return $row;
        }

        $defaults = self::SYSTEM_DEFAULTS;

        if (Schema::hasColumn('users', 'reader_default_font')
            && $user->reader_default_font !== null) {
            $defaults['font_family'] = $user->reader_default_font;
        }

        if (Schema::hasColumn('users', 'reader_default_size')
            && $user->reader_default_size !== null) {
            $defaults['font_size'] = (int) $user->reader_default_size;
        }

        return new ReaderSetting(array_merge(
            ['user_id' => $user->id, 'book_id' => $book->id],
            $defaults
        ));
    }

    /** Save per-book settings (only this book's row ever changes, §1.2). */
    public function saveSettings(User $user, Book $book, array $data): ReaderSetting
    {
        $clean = [
            'font_family' => in_array($data['font_family'] ?? null, ['serif', 'sans'], true)
                ? $data['font_family'] : self::SYSTEM_DEFAULTS['font_family'],
            'font_size' => min(200, max(80, (int) ($data['font_size'] ?? 100))),
            'line_height' => min(2.20, max(1.40, (float) ($data['line_height'] ?? 1.60))),
            'theme' => in_array($data['theme'] ?? null, ['paper', 'sepia', 'night'], true)
                ? $data['theme'] : 'paper',
            'brightness' => min(100, max(50, (int) ($data['brightness'] ?? 100))),
            'reading_mode' => in_array($data['reading_mode'] ?? null, ['paged', 'scroll'], true)
                ? $data['reading_mode'] : 'paged',
        ];

        return ReaderSetting::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            $clean
        );
    }

    public function progressFor(User $user, Book $book): ?ReadingProgress
    {
        return ReadingProgress::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->first();
    }

    /**
     * Save progress. Conflict rule (§1.3): the server wins by
     * last_read_at — a save older than the stored row is ignored.
     * total_minutes only ever grows (minutes are cumulative).
     * Reaching 100% sets finished_at (X71 / X82).
     */
    public function saveProgress(User $user, Book $book, array $data): ReadingProgress
    {
        $incomingAt = isset($data['last_read_at'])
            ? now()->parse($data['last_read_at'])
            : now();

        $existing = $this->progressFor($user, $book);

        if ($existing !== null && $existing->last_read_at !== null
            && $incomingAt->lt($existing->last_read_at)) {
            return $existing;
        }

        $percent = min(100, max(0, (int) ($data['percent'] ?? $existing->percent ?? 0)));

        $progress = ReadingProgress::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            [
                'cfi' => $data['cfi'] ?? $existing?->cfi,
                'percent' => $percent,
                'chapter_index' => max(0, (int) ($data['chapter_index'] ?? $existing->chapter_index ?? 0)),
                'total_minutes' => max(
                    $existing->total_minutes ?? 0,
                    max(0, (int) ($data['total_minutes'] ?? $existing->total_minutes ?? 0))
                ),
                'last_read_at' => $incomingAt,
            ]
        );

        $wasFinished = $existing !== null && $existing->finished_at !== null;

        if ($percent >= 100 && $progress->finished_at === null) {
            $progress->finished_at = $incomingAt;
            $progress->save();
        }

        // Phase 3 (§2.3): feed the monthly reading-minutes ledger that
        // the subscription pool is split from. Never blocks the save.
        // Low-2 preview guard: an author's own-book reads NEVER feed
        // the ledger — skipped here at the call site, so every progress
        // entry point (web PUT, API, Livewire) is covered in one place.
        // recordReadingMinutes() refuses own-book rows too (spec §2.2
        // rule 4) and closePeriod() re-checks — defence in depth.
        if (! $this->isOwnBook($user, $book)) {
            $this->recordReadingMinutes($user, $book, (int) ($existing->total_minutes ?? 0), (int) $progress->total_minutes);
        }

        // Phase 4 §4.5 — badge hooks only; no other progress rule changes.
        // (ReaderService has no finished() method — finish state is set
        // here at 100%, so the book_finished hook lives here too.)
        if (class_exists(BadgeService::class) && Schema::hasTable('badges')) {
            $badges = app(BadgeService::class);
            $badges->checkAndAward($user, 'progress_saved');
            if (! $wasFinished && $progress->finished_at !== null) {
                $badges->checkAndAward($user, 'book_finished');
            }
        }

        return $progress->fresh();
    }

    /** Is this user the book's author? (books.author_id → authors.user_id.) */
    protected function isOwnBook(User $user, Book $book): bool
    {
        if ($book->author_id === null) {
            return false;
        }

        $authorUserId = DB::table('authors')->where('id', $book->author_id)->value('user_id');

        return $authorUserId !== null && (int) $authorUserId === (int) $user->id;
    }

    /**
     * Phase 3 (§2.3) — Builder A. Record the delta minutes
     * (new total_minutes − old total_minutes) into the current month's
     * reading_minutes row (user, book, author, period YYYY-MM).
     *
     * Rules: minutes only ever grow; the author's own book is NEVER
     * recorded (spec §2.2 rule 4). Tolerates Builder B's table not
     * existing yet — the call is then a no-op, not an error.
     */
    public function recordReadingMinutes(User $user, Book $book, int $oldTotalMinutes, int $newTotalMinutes): void
    {
        $delta = $newTotalMinutes - $oldTotalMinutes;

        if ($delta <= 0) {
            return;
        }

        if (! Schema::hasTable('reading_minutes')) {
            return;
        }

        // Never record minutes for the author's own book.
        $authorUserId = DB::table('authors')->where('id', $book->author_id)->value('user_id');
        if ($authorUserId !== null && (int) $authorUserId === (int) $user->id) {
            return;
        }

        $period = now()->format('Y-m');

        DB::table('reading_minutes')->upsert(
            [[
                'user_id' => $user->id,
                'book_id' => $book->id,
                'author_id' => $book->author_id,
                'period' => $period,
                'minutes' => $delta,
                'counted' => false,
                'flagged' => false,
                'flag_note' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id', 'book_id', 'period'],
            ['minutes' => DB::raw('reading_minutes.minutes + '.$delta), 'updated_at' => now()]
        );
    }

    /** Pool qualification (§1.3): >= 10 minutes OR >= 10% read. Pool math is Phase 3. */
    public function qualifiesForPool(ReadingProgress $progress): bool
    {
        return $progress->total_minutes >= 10 || $progress->percent >= 10;
    }

    public function addBookmark(User $user, Book $book, string $cfi, ?string $label = null): Bookmark
    {
        return Bookmark::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'cfi' => $cfi,
            'label' => $label,
        ]);
    }

    public function removeBookmark(User $user, Bookmark $bookmark): void
    {
        abort_unless($bookmark->user_id === $user->id, 403);
        $bookmark->delete();
    }

    /** @return Collection<int, Bookmark> */
    public function listBookmarks(User $user, ?Book $book = null): Collection
    {
        return Bookmark::where('user_id', $user->id)
            ->when($book !== null, fn ($q) => $q->where('book_id', $book->id))
            ->with('book')
            ->latest()
            ->get();
    }

    public function addHighlight(User $user, Book $book, string $cfiStart, string $cfiEnd, string $color = 'yellow', ?string $note = null): Highlight
    {
        return Highlight::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'cfi_start' => $cfiStart,
            'cfi_end' => $cfiEnd,
            'color' => in_array($color, ['yellow', 'green', 'blue', 'pink'], true) ? $color : 'yellow',
            'note' => $note,
        ]);
    }

    public function removeHighlight(User $user, Highlight $highlight): void
    {
        abort_unless($highlight->user_id === $user->id, 403);
        $highlight->delete();
    }

    public function addNote(User $user, Book $book, string $cfi, string $body): Note
    {
        return Note::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'cfi' => $cfi,
            'body' => $body,
        ]);
    }

    public function removeNote(User $user, Note $note): void
    {
        abort_unless($note->user_id === $user->id, 403);
        $note->delete();
    }
}
