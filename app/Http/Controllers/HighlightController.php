<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Highlight;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

class HighlightController extends Controller
{
    public function __construct(
        protected BookService $books,
        protected ReaderService $reader,
    ) {}

    public function store(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        $data = $request->validate([
            'cfi_start' => ['required', 'string', 'max:2000'],
            'cfi_end' => ['required', 'string', 'max:2000'],
            'color' => ['nullable', 'in:yellow,green,blue,pink'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $highlight = $this->reader->addHighlight(
            $request->user(), $book,
            $data['cfi_start'], $data['cfi_end'],
            $data['color'] ?? 'yellow', $data['note'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json(['highlight' => $highlight], 201);
        }

        return back()->with('status', __('reader.highlight_saved'));
    }

    public function destroy(Request $request, string $locale, Book $book, Highlight $highlight)
    {
        abort_unless($highlight->book_id === $book->id, 404);
        abort_unless($highlight->user_id === $request->user()->id, 403);

        // Hard delete — no soft delete (§1.4).
        $highlight->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('status', __('reader.highlight_deleted'));
    }
}
