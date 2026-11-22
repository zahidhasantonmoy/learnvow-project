<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\BookService;
use Illuminate\Http\Request;

class TtsController extends Controller
{
    use BuildsSeo;

    public function __construct(protected BookService $books) {}

    /**
     * Text-to-speech page (§1.6). Browser Web Speech API, client-side
     * (bn + en voices) — no server cost, no audio file needed. The
     * chapter text is read from the EPUB in the browser, never sent
     * to a server.
     */
    public function show(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        return view('audio.tts', [
            'book' => $book,
            'seo' => $this->seo(__('reader.tts_title'), null, ['noindex' => true]),
        ]);
    }
}
