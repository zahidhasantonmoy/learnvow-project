<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\BookFileService;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

class ReaderController extends Controller
{
    use BuildsSeo;

    public function __construct(
        protected BookService $books,
        protected ReaderService $reader,
        protected BookFileService $files,
    ) {}

    private function denyUnlessCanRead(Request $request, Book $book)
    {
        if (! $this->books->canRead($request->user(), $book)) {
            abort(403);
        }
    }

    /** Reader page (spec §1.1) — server-rendered Blade; epub.js loads chapters client-side. */
    public function show(Request $request, string $locale, Book $book)
    {
        $this->denyUnlessCanRead($request, $book);

        $user = $request->user();
        $progress = $this->reader->progressFor($user, $book);
        $settings = $this->reader->settingsFor($user, $book);
        $epub = $this->files->fileFor($book, 'epub');
        $audio = $this->files->fileFor($book, 'audio');

        return view('reader.show', [
            'book' => $book,
            'progress' => $progress,
            'settings' => $settings,
            'hasEpub' => $epub !== null,
            'hasAudioFile' => $audio !== null,
            'bookmarks' => $this->reader->listBookmarks($user, $book),
            'seo' => $this->seo($book->title, null, ['noindex' => true]),
        ]);
    }

    /**
     * The ONLY way to reach a book file (§1.1): login + canRead check,
     * then redirect to the 15-minute signed URL. The signed URL never
     * appears in the page HTML/JS — only in this redirect's Location.
     */
    public function file(Request $request, string $locale, Book $book)
    {
        $this->denyUnlessCanRead($request, $book);

        if ($this->files->fileFor($book, 'epub') === null) {
            abort(404);
        }

        return redirect()->away($this->files->signedFileUrl($book, 'epub'));
    }

    /**
     * Signed-file endpoint (route name `reader.file.signed`, `signed`
     * middleware). Re-checks canRead, then streams via BookFileService.
     * The {book} segment holds the numeric id here (BookFileService
     * builds the signed URL with $book->id), so resolve id-or-slug.
     */
    public function signedFile(Request $request, string $locale, string $book, string $type)
    {
        abort_unless(in_array($type, ['epub', 'audio'], true), 404);

        $resolved = Book::where('id', $book)->orWhere('slug', $book)->firstOrFail();

        $this->denyUnlessCanRead($request, $resolved);

        $file = $this->files->fileFor($resolved, $type);
        abort_unless($file !== null, 404);

        return $this->files->streamFile($file);
    }

    /** Per-book reading settings page (§1.2). */
    public function settings(Request $request, string $locale, Book $book)
    {
        $this->denyUnlessCanRead($request, $book);

        return view('reader.settings', [
            'book' => $book,
            'settings' => $this->reader->settingsFor($request->user(), $book),
            'seo' => $this->seo(__('reader.settings_title'), null, ['noindex' => true]),
        ]);
    }

    /** Save progress: page change + every 30s debounce (§1.3). */
    public function progress(Request $request, string $locale, Book $book)
    {
        $this->denyUnlessCanRead($request, $book);

        $data = $request->validate([
            'cfi' => ['nullable', 'string', 'max:2000'],
            'percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'chapter_index' => ['nullable', 'integer', 'min:0'],
            'total_minutes' => ['nullable', 'integer', 'min:0'],
            'last_read_at' => ['nullable', 'date'],
        ]);

        $progress = $this->reader->saveProgress($request->user(), $book, $data);

        return response()->json([
            'percent' => $progress->percent,
            'total_minutes' => $progress->total_minutes,
            'finished' => $progress->isFinished(),
            'finished_at' => $progress->finished_at?->toISOString(),
        ]);
    }

    /** Book-finished screen (X71) — shown at 100%. */
    public function finished(Request $request, string $locale, Book $book)
    {
        $this->denyUnlessCanRead($request, $book);

        $progress = $this->reader->progressFor($request->user(), $book);

        if ($progress === null || ! $progress->isFinished()) {
            return redirect()->route('reader.show', [
                'locale' => app()->getLocale(), 'book' => $book->slug,
            ]);
        }

        $nextBook = Book::published()
            ->where('category_id', $book->category_id)
            ->where('id', '!=', $book->id)
            ->orderByDesc('ratings_count')
            ->first();

        return view('reader.finished', [
            'book' => $book,
            'progress' => $progress,
            'nextBook' => $nextBook,
            'seo' => $this->seo(__('reader.finished_title'), null, ['noindex' => true]),
        ]);
    }
}
