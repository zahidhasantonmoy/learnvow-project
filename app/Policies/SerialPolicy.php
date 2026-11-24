<?php

namespace App\Policies;

use App\Models\Serial;
use App\Models\User;
use App\Services\AdminService;

/** Serials: anyone may view; only the owning author edits. */
class SerialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Serial $serial): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return \App\Models\Author::where('user_id', $user->id)->exists()
            || AdminService::isAdmin($user);
    }

    public function update(User $user, Serial $serial): bool
    {
        return $serial->author?->user_id === $user->id || AdminService::isAdmin($user);
    }

    public function delete(User $user, Serial $serial): bool
    {
        return $serial->author?->user_id === $user->id || AdminService::isAdmin($user);
    }
}
