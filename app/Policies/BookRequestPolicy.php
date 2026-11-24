<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;
use App\Services\AdminService;

/** Own requests; Admin may view/review any. */
class BookRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BookRequest $request): bool
    {
        return $request->user_id === $user->id || AdminService::isAdmin($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BookRequest $request): bool
    {
        return $request->user_id === $user->id || AdminService::isAdmin($user);
    }

    public function delete(User $user, BookRequest $request): bool
    {
        return $request->user_id === $user->id || AdminService::isAdmin($user);
    }
}
