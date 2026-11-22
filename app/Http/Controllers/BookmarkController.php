<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Bookmark;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    use BuildsSeo;

    public function __construct(
        protected BookService $books,
        protected ReaderService $reader,
    ) {}

    /** All books' bookmarks / highlights / notes in one list (§1.4). */
    public function index(Request $request, string $locale)
    {
        $user = $request->user();

        return view('reader.bookmarks', [
            'bookmarks' => $this->reader->listBookmarks($user),
            'highlights' => \App\Models\Highlight::where('user_id', $user->id)
                ->with('book')->latest()->get(),
            'notes' => \App\Models\Note::where('user_id', $user->id)
                ->with('book')->latest()->get(),
            'seo' => $this->seo(__('reader.bookmarks_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        $data = $request->validate([
            'cfi' => ['required', 'string', 'max:2000'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $bookmark = $this->reader->addBookmark(
            $request->user(), $book, $data['cfi'], $data['label'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json(['bookmark' => $bookmark], 201);
        }

        return back()->with('status', __('reader.bookmark_saved'));
    }

    public function destroy(Request $request, string $locale, Book $book, Bookmark $bookmark)
    {
        abort_unless($bookmark->book_id === $book->id, 404);
        abort_unless($bookmark->user_id === $request->user()->id, 403);

        // Hard delete — no soft delete (§1.4).
        $bookmark->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('status', __('reader.bookmark_deleted'));
    }
}
