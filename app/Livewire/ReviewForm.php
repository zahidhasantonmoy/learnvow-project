<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Review;
use App\Services\BookService;
use Livewire\Component;

class ReviewForm extends Component
{
    public Book $book;
    public int $rating = 5;
    public string $title = '';
    public string $body = '';
    public bool $submitted = false;
    public bool $canReview = false;

    public function mount(Book $book, BookService $books): void
    {
        $this->book = $book;
        // Preview (Low-2) is not a review entitlement: the author may
        // read their own book but never review it.
        $this->canReview = $books->canRead(auth()->user(), $book)
            && ! $books->isOwnBook(auth()->user(), $book);
        $existing = auth()->user()
            ? Review::where('user_id', auth()->id())->where('book_id', $book->id)->first()
            : null;
        if ($existing) {
            $this->rating = $existing->rating;
            $this->title = $existing->title ?? '';
            $this->body = $existing->body;
        }
    }

    public function submit(BookService $books): void
    {
        $user = auth()->user();
        if (! $user || ! $books->canRead($user, $this->book)) {
            $this->addError('review', __('books.review_requires_entitlement'));
            return;
        }

        // Low-2 consequence: preview is not a review entitlement.
        if ($books->isOwnBook($user, $this->book)) {
            $this->addError('review', __('books.review_own_book'));
            return;
        }

        $validated = $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        Review::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $this->book->id],
            array_merge($validated, ['status' => 'pending'])
        );

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.review-form');
    }
}
