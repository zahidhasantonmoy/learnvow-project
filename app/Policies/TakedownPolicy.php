<?php

namespace App\Policies;

use App\Models\TakedownRequest;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §5.3, §12. Copyright takedown claims.
 * Anyone logged in may file a claim; only Admin / Super Admin decide.
 */
class TakedownPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function view(User $user, TakedownRequest $request): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return true; // Any logged-in user may file a claim.
    }

    public function decide(User $user, TakedownRequest $request): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, TakedownRequest $request): bool
    {
        return false;
    }
}
