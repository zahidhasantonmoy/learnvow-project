<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\Highlight;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

/** Highlights — web mirror (spec §1.3). */
class HighlightController extends ApiController
{
    public function index(Request $request, Book $book)
    {
        $rows = Highlight::where('user_id', $request->user()->id)->where('book_id', $book->id)->latest()->get();

        return $this->ok($rows->map(fn (Highlight $h) => [
            'id' => $h->id, 'book_id' => $h->book_id, 'cfi_start' => $h->cfi_start,
            'cfi_end' => $h->cfi_end, 'color' => $h->color, 'note' => $h->note,
            'created_at' => $h->created_at?->toIso8601String(),
        ])->values());
    }

    public function store(Request $request, Book $book, ReaderService $reader, BookService $books)
    {
        // SEC-08: same canRead gate as saveProgress/file/download.
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }

        $data = $request->validate([
            'cfi_start' => 'required|string|max:500', 'cfi_end' => 'required|string|max:500',
            'color' => 'nullable|in:yellow,green,blue,pink', 'note' => 'nullable|string|max:1000',
        ]);
        $highlight = $reader->addHighlight($request->user(), $book, $data['cfi_start'], $data['cfi_end'], $data['color'] ?? 'yellow', $data['note'] ?? null);

        return $this->ok(['id' => $highlight->id, 'book_id' => $highlight->book_id, 'cfi_start' => $highlight->cfi_start, 'cfi_end' => $highlight->cfi_end, 'color' => $highlight->color, 'note' => $highlight->note], 201);
    }

    public function destroy(Request $request, Book $book, Highlight $highlight, ReaderService $reader)
    {
        if ((int) $highlight->user_id !== (int) $request->user()->id || (int) $highlight->book_id !== (int) $book->id) {
            abort(403);
        }
        $reader->removeHighlight($request->user(), $highlight);

        return $this->ok(['deleted' => true]);
    }
}
