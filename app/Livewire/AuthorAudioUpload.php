<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\AuthorService;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Chapter-by-chapter audio upload (§1.5, §11). One audio file per book. */
class AuthorAudioUpload extends Component
{
    use WithFileUploads;

    public Book $book;
    public $audio = null;
    public ?string $error = null;
    public bool $uploaded = false;

    public function mount(Book $book): void
    {
        $this->book = $book;
    }

    public function submit(AuthorService $authors)
    {
        $this->error = null;
        $this->uploaded = false;
        $this->validate([
            'audio' => 'required|file|mimetypes:audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg|max:512000',
        ], [
            // Round 3: novalidate lets Livewire run, so the required
            // error must be localized, not Laravel's English default.
            'audio.required' => __('author.audio_file_required'),
        ]);

        $author = $authors->approvedAuthorFor(auth()->user());
        if ($author === null) {
            abort(403);
        }

        try {
            $authors->addAudio($author, $this->book, $this->audio);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->book = $this->book->fresh();
        $this->uploaded = true;
    }

    public function render()
    {
        return view('livewire.author-audio-upload');
    }
}
