<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Services\BookService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, string $locale, string $slug, BookService $books)
    {
        $book = $books->show($slug);
        $user = $request->user();

        // Only a Reader WITH entitlement may review (spec §1.7).
        if (! $books->canRead($user, $book)) {
            return back()->withErrors([
                'review' => __('books.review_requires_entitlement'),
            ])->withInput();
        }

        // Low-2 consequence: canRead() now admits the book's own author
        // (preview) — an author must still never review their own book.
        if ($books->isOwnBook($user, $book)) {
            return back()->withErrors([
                'review' => __('books.review_own_book'),
            ])->withInput();
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $review = Review::updateOrCreate(
            ['user_id' => $user->id, 'book_id' => $book->id],
            array_merge($validated, ['status' => 'pending'])
        );

        return back()->with('status', __('books.review_submitted_pending'));
    }
}
