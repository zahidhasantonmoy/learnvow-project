<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Review $review): bool
    {
        return $review->status === 'approved'
            || ($user !== null && $this->moderate($user, $review));
    }

    /**
     * Write a review = a Reader who holds an entitlement for this book
     * (bought it, read it free, or via Premium — spec §1.7).
     * Checked against user_books, never against a file URL.
     */
    public function create(User $user, Book $book): bool
    {
        if (! $user->hasRole('reader')) {
            return false;
        }

        if (! Schema::hasTable('user_books')) {
            return false;
        }

        return DB::table('user_books')
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->exists();
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $this->moderate($user, $review);
    }

    public function moderate(User $user, Review $review): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin', 'support']);
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $this->moderate($user, $review);
    }
}
