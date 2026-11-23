<?php

namespace App\Policies;

use App\Models\AuthorEarning;
use App\Models\User;

class EarningPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['author', 'super-admin']);
    }

    /** An author sees only their own earnings; the Super Admin may see any. */
    public function view(User $user, AuthorEarning $earning): bool
    {
        return $user->hasRole('super-admin')
            || (int) $earning->author->user_id === (int) $user->id;
    }
}
