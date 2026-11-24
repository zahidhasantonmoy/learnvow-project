<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * ReaderView — the reader's main component (§10): page changes,
 * progress save (debounced), bookmark / highlight toggles, settings
 * applied. The EPUB itself is rendered by epub.js in reader/show
 * (resources/js/reader.js), which calls these methods via $wire.
 */
class ReaderView extends Component
{
    public Book $book;

    public ?string $cfi = null;

    public int $percent = 0;

    public int $chapterIndex = 0;

    public int $totalMinutes = 0;

    public bool $finished = false;

    public string $vocabWord = '';

    public ?string $vocabMeaning = null;

    public function mount(Book $book): void
    {
        $this->book = $book;

        $progress = app(ReaderService::class)->progressFor(Auth::user(), $book);

        if ($progress !== null) {
            $this->cfi = $progress->cfi;
            $this->percent = $progress->percent;
            $this->chapterIndex = $progress->chapter_index;
            $this->totalMinutes = $progress->total_minutes;
            $this->finished = $progress->isFinished();
        }
    }

    /** Save progress (called on page change + every 30s, debounced client-side). */
    public function saveProgress(?string $cfi, int $percent, int $chapterIndex, int $totalMinutes): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);

        $progress = app(ReaderService::class)->saveProgress(Auth::user(), $this->book, [
            'cfi' => $cfi,
            'percent' => $percent,
            'chapter_index' => $chapterIndex,
            'total_minutes' => $totalMinutes,
            'last_read_at' => now()->toISOString(),
        ]);

        $this->cfi = $progress->cfi;
        $this->percent = $progress->percent;
        $this->chapterIndex = $progress->chapter_index;
        $this->totalMinutes = $progress->total_minutes;
        $this->finished = $progress->isFinished();

        if ($this->finished) {
            $this->dispatch('book-finished', book: $this->book->slug);
        }
    }

    public function addBookmark(?string $cfi, ?string $label = null): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);
        abort_unless($cfi !== null && $cfi !== '', 422);

        app(ReaderService::class)->addBookmark(Auth::user(), $this->book, $cfi, $label);
        $this->dispatch('bookmark-saved');
    }

    /**
     * The reader page adds bookmarks via the REST endpoint (reader.js holds
     * the EPUB CFI, which Livewire does not know). After a successful POST,
     * reader.js dispatches `bookmark-added`; handling it re-renders this
     * component, so render() re-queries listBookmarks() and the on-page
     * bookmark list refreshes without a manual reload.
     */
    #[On('bookmark-added')]
    public function refreshBookmarks(): void
    {
        // No state to change — the event alone triggers the re-render.
    }

    public function addHighlight(string $cfiStart, string $cfiEnd, string $color = 'yellow', ?string $note = null): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);

        app(ReaderService::class)->addHighlight(Auth::user(), $this->book, $cfiStart, $cfiEnd, $color, $note);
        $this->dispatch('highlight-saved');
    }

    /**
     * Phase 4 §4.11 — save a looked-up dictionary word to the
     * vocabulary. Called from the dictionary popup on the reader page
     * (reader.js shows the meaning; this stores it). One row per
     * user+word (stored lowercase); a repeat save is a no-op.
     */
    public function addToVocabulary(string $word, ?string $meaningEn = null, ?string $meaningBn = null): void
    {
        $word = mb_strtolower(trim($word));
        abort_unless($word !== '' && mb_strlen($word) <= 100, 422);

        \App\Models\VocabularyWord::firstOrCreate(
            ['user_id' => Auth::user()->id, 'word' => $word],
            [
                'meaning_en' => $meaningEn,
                'meaning_bn' => $meaningBn,
                'book_id' => $this->book->id,
                'cfi' => $this->cfi,
            ]
        );

        $this->dispatch('vocabulary-saved', word: $word);
    }

    /** Dictionary popup form submit — stores the typed word. */
    public function saveVocabulary(): void
    {
        $this->addToVocabulary($this->vocabWord, $this->vocabMeaning);
        $this->reset(['vocabWord', 'vocabMeaning']);
    }

    public function addNote(string $cfi, string $body): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);

        app(ReaderService::class)->addNote(Auth::user(), $this->book, $cfi, $body);
        $this->dispatch('note-saved');
    }

    public function render()
    {
        return view('livewire.reader-view', [
            'bookmarks' => app(ReaderService::class)->listBookmarks(Auth::user(), $this->book),
        ]);
    }
}
