<?php

namespace App\Policies;

use App\Models\Preorder;
use App\Models\User;

/** Own pre-orders only. */
class PreorderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Preorder $preorder): bool
    {
        return $preorder->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Preorder $preorder): bool
    {
        return $preorder->user_id === $user->id;
    }

    public function delete(User $user, Preorder $preorder): bool
    {
        return $preorder->user_id === $user->id;
    }
}
