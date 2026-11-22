<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Book $book): bool
    {
        return $book->status === 'published'
            || ($user !== null && $this->approve($user, $book));
    }

    public function create(User $user): bool
    {
        return $user->hasRole('author');
    }

    /**
     * Edit own book = that book's author (spec §1.2). The owner
     * (super-admin) may also edit.
     */
    public function update(User $user, Book $book): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $author = Author::query()->whereKey($book->author_id)->first();

        return $author !== null && $author->user_id !== null && $author->user_id === $user->id;
    }

    public function approve(User $user, Book $book): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function reject(User $user, Book $book): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->hasRole('super-admin');
    }
}
