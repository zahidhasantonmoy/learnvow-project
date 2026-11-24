<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\Note;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

/** Notes — web mirror (spec §1.3). */
class NoteController extends ApiController
{
    public function index(Request $request, Book $book)
    {
        $rows = Note::where('user_id', $request->user()->id)->where('book_id', $book->id)->latest()->get();

        return $this->ok($rows->map(fn (Note $n) => [
            'id' => $n->id, 'book_id' => $n->book_id, 'cfi' => $n->cfi, 'body' => $n->body,
            'created_at' => $n->created_at?->toIso8601String(),
        ])->values());
    }

    public function store(Request $request, Book $book, ReaderService $reader, BookService $books)
    {
        // SEC-08: same canRead gate as saveProgress/file/download.
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }

        $data = $request->validate(['cfi' => 'required|string|max:500', 'body' => 'required|string|max:5000']);
        $note = $reader->addNote($request->user(), $book, $data['cfi'], $data['body']);

        return $this->ok(['id' => $note->id, 'book_id' => $note->book_id, 'cfi' => $note->cfi, 'body' => $note->body], 201);
    }

    public function destroy(Request $request, Book $book, Note $note, ReaderService $reader)
    {
        if ((int) $note->user_id !== (int) $request->user()->id || (int) $note->book_id !== (int) $book->id) {
            abort(403);
        }
        $reader->removeNote($request->user(), $note);

        return $this->ok(['deleted' => true]);
    }
}
