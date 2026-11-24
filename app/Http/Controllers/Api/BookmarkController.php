<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\Bookmark;
use App\Services\ReaderService;
use Illuminate\Http\Request;

/** Bookmarks — web mirror (spec §1.3). */
class BookmarkController extends ApiController
{
    public function index(Request $request, Book $book, ReaderService $reader)
    {
        return $this->ok($reader->listBookmarks($request->user(), $book)->map(fn (Bookmark $b) => [
            'id' => $b->id, 'book_id' => $b->book_id, 'cfi' => $b->cfi, 'label' => $b->label,
            'created_at' => $b->created_at?->toIso8601String(),
        ])->values());
    }

    public function store(Request $request, Book $book, ReaderService $reader)
    {
        $data = $request->validate(['cfi' => 'required|string|max:500', 'label' => 'nullable|string|max:255']);
        $bookmark = $reader->addBookmark($request->user(), $book, $data['cfi'], $data['label'] ?? null);

        return $this->ok(['id' => $bookmark->id, 'book_id' => $bookmark->book_id, 'cfi' => $bookmark->cfi, 'label' => $bookmark->label], 201);
    }

    public function destroy(Request $request, Book $book, Bookmark $bookmark, ReaderService $reader)
    {
        if ((int) $bookmark->user_id !== (int) $request->user()->id || (int) $bookmark->book_id !== (int) $book->id) {
            abort(403);
        }
        $reader->removeBookmark($request->user(), $bookmark);

        return $this->ok(['deleted' => true]);
    }
}
