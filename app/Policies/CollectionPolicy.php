<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;

/** Own shelves only — except public shelves anyone logged in may view. */
class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $collection): bool
    {
        return $collection->user_id === $user->id || $collection->is_public;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Collection $collection): bool
    {
        return $collection->user_id === $user->id;
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $collection->user_id === $user->id;
    }
}
