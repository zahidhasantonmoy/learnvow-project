<?php

namespace App\Policies;

use App\Models\Author;
use App\Models\AuthorApplication;
use App\Models\Book;
use App\Models\User;

class AuthorPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Author $author): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('reader');
    }

    /**
     * An author edits their own profile; the owner may edit any.
     */
    public function update(User $user, Author $author): bool
    {
        return $user->hasRole('super-admin') || $author->user_id === $user->id;
    }

    public function approve(User $user, Author $author): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function reject(User $user, Author $author): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Author $author): bool
    {
        return $user->hasRole('super-admin');
    }

    // ------------------------------------------------------------------
    // Phase 3 — Builder A. Authors see ONLY their own data, enforced by
    // policy, not just by hiding UI (spec §0.9).
    // ------------------------------------------------------------------

    /** Own application only (admin/super-admin may review any). */
    public function viewApplication(User $user, AuthorApplication $application): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin'])
            || $application->user_id === $user->id;
    }

    /** Publish: approved author only (role + approved Author row). */
    public function publish(User $user): bool
    {
        if (! $user->hasRole('author')) {
            return false;
        }

        return Author::where('user_id', $user->id)->where('status', 'approved')->exists();
    }

    /** Manage one of the author's own books (edit / price / pause / audio / print). */
    public function manageBook(User $user, Book $book): bool
    {
        $author = Author::where('user_id', $user->id)->first();

        return $author !== null && (int) $book->author_id === (int) $author->id;
    }

    /** The Author Studio dashboard itself: approved author only. */
    public function viewStudio(User $user): bool
    {
        return $this->publish($user);
    }
}
