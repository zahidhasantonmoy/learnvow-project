<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

/** A token belongs to its user — nobody else may view or revoke it. */
class ApiTokenPolicy
{
    public function view(User $user, PersonalAccessToken $token): bool
    {
        return $token->tokenable_type === User::class
            && (int) $token->tokenable_id === (int) $user->id;
    }

    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $this->view($user, $token);
    }
}
