<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Book;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AuthorAudioController extends Controller
{
    use BuildsSeo;

    public function create(string $locale, Book $book, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);

        return view('author.books.audio', [
            'book' => $book,
            'seo' => $this->seo(__('author.audio_title'), null, ['noindex' => true]),
        ]);
    }

    /** Non-Livewire fallback submit (the form is Livewire AuthorAudioUpload). */
    public function store(string $locale, Book $book, Request $request, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);

        $request->validate(['audio' => 'required|file|mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg|max:512000']);

        try {
            $authors->addAudio($authors->approvedAuthorFor($request->user()), $book, $request->file('audio'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['audio' => $e->getMessage()]);
        }

        return back()->with('status', __('author.audio_success'));
    }
}
