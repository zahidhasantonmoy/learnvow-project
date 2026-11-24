<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

/** Clubs: anyone logged in may view a public club; members only inside. */
class ClubPolicy
{
    public function view(?User $user, Club $club): bool
    {
        if ($club->is_public) {
            return true;
        }

        return $user !== null && $club->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function join(User $user, Club $club): bool
    {
        return $club->is_public && ! $club->hasMember($user) && ! $club->isFull();
    }

    public function post(User $user, Club $club): bool
    {
        return $club->hasMember($user);
    }

    /** Creator (or Admin) manages the club. */
    public function manage(User $user, Club $club): bool
    {
        return (int) $club->creator_id === (int) $user->id
            || $user->hasAnyRole(['admin', 'super-admin']);
    }
}
