<?php

namespace App\Policies;

use App\Models\AuthorPayout;
use App\Models\User;

class PayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['author', 'super-admin']);
    }

    /** An author sees only their own payouts; the Super Admin may see any. */
    public function view(User $user, AuthorPayout $payout): bool
    {
        return $user->hasRole('super-admin')
            || (int) $payout->author->user_id === (int) $user->id;
    }

    /** Approve / release / fail / retry = Super Admin ONLY (spec §0.7, §2.4). */
    public function approve(User $user, AuthorPayout $payout): bool
    {
        return $user->hasRole('super-admin');
    }

    public function release(User $user, AuthorPayout $payout): bool
    {
        return $user->hasRole('super-admin');
    }

    public function fail(User $user, AuthorPayout $payout): bool
    {
        return $user->hasRole('super-admin');
    }

    public function retry(User $user, AuthorPayout $payout): bool
    {
        return $user->hasRole('super-admin');
    }
}
