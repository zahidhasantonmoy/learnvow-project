<?php

namespace App\Policies;

use App\Models\ReadingGoal;
use App\Models\User;

/** Own reading goals only. */
class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ReadingGoal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ReadingGoal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    public function delete(User $user, ReadingGoal $goal): bool
    {
        return $goal->user_id === $user->id;
    }
}
