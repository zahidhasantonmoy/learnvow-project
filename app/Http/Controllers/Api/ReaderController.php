<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Services\BookFileService;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Http\Request;

/** Reading progress + signed file URL (spec §1.3). */
class ReaderController extends ApiController
{
    public function progress(Request $request, Book $book, ReaderService $reader)
    {
        $progress = $reader->progressFor($request->user(), $book);

        return $this->ok($progress ? $this->progressArray($progress) : null);
    }

    public function saveProgress(Request $request, Book $book, ReaderService $reader, BookService $books)
    {
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }

        $data = $request->validate([
            'cfi' => 'nullable|string|max:500',
            'percent' => 'nullable|integer|min:0|max:100',
            'chapter_index' => 'nullable|integer|min:0',
            'total_minutes' => 'nullable|integer|min:0',
            'last_read_at' => 'nullable|date',
        ]);

        $progress = $reader->saveProgress($request->user(), $book, $data);

        return $this->ok($this->progressArray($progress));
    }

    public function file(Request $request, Book $book, BookFileService $files, BookService $books)
    {
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }
        if ($files->fileFor($book, 'epub') === null) {
            abort(404);
        }

        return $this->ok(['url' => $files->signedFileUrl($book, 'epub'), 'expires_in_minutes' => 15]);
    }

    private function progressArray(\App\Models\ReadingProgress $progress): array
    {
        return [
            'book_id' => $progress->book_id,
            'cfi' => $progress->cfi,
            'percent' => (int) $progress->percent,
            'chapter_index' => (int) $progress->chapter_index,
            'total_minutes' => (int) $progress->total_minutes,
            'finished_at' => $progress->finished_at?->toIso8601String(),
            'last_read_at' => $progress->last_read_at?->toIso8601String(),
        ];
    }
}
