<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Note;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(
        protected BookService $books,
        protected ReaderService $reader,
    ) {}

    public function store(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        $data = $request->validate([
            'cfi' => ['required', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $note = $this->reader->addNote($request->user(), $book, $data['cfi'], $data['body']);

        if ($request->expectsJson()) {
            return response()->json(['note' => $note], 201);
        }

        return back()->with('status', __('reader.note_saved'));
    }

    public function destroy(Request $request, string $locale, Book $book, Note $note)
    {
        abort_unless($note->book_id === $book->id, 404);
        abort_unless($note->user_id === $request->user()->id, 403);

        // Hard delete — no soft delete (§1.4).
        $note->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('status', __('reader.note_deleted'));
    }
}
