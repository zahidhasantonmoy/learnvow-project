<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\BookFileService;
use App\Services\BookService;
use Illuminate\Http\Request;

class AudioController extends Controller
{
    use BuildsSeo;

    public function __construct(
        protected BookService $books,
        protected BookFileService $files,
    ) {}

    /**
     * Audiobook player (§1.6). No seed audio files exist (§7.2), so a
     * has_audiobook book with no audio row shows "coming soon" + a TTS
     * button — never a broken player.
     */
    public function show(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        $audio = $this->files->fileFor($book, 'audio');

        // The <audio> src is the file route, NOT the signed URL: the
        // route redirects to a fresh 15-minute signed URL (§1.1 — the
        // signed URL never appears in page HTML/JS).
        return view('audio.player', [
            'book' => $book,
            'hasAudioFile' => $audio !== null,
            'audioUrl' => $audio !== null
                ? route('audio.file', ['locale' => app()->getLocale(), 'book' => $book->slug])
                : null,
            'seo' => $this->seo(__('reader.audio_title'), null, ['noindex' => true]),
        ]);
    }

    /** Audio file via the same signed-URL redirect as the EPUB (§1.6). */
    public function file(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        if ($this->files->fileFor($book, 'audio') === null) {
            abort(404);
        }

        return redirect()->away($this->files->signedFileUrl($book, 'audio'));
    }
}
