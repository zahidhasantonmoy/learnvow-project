<?php

namespace App\Policies;

use App\Models\Author;
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
}
