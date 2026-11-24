<?php

namespace App\Policies;

use App\Models\TaxKycProfile;
use App\Models\User;

/** KYC: only the author themself, or Super Admin (spec §3.3). */
class TaxKycPolicy
{
    public function view(User $user, TaxKycProfile $profile): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $profile->author !== null
            && $profile->author->user_id !== null
            && (int) $profile->author->user_id === (int) $user->id;
    }

    public function update(User $user, TaxKycProfile $profile): bool
    {
        return $this->view($user, $profile);
    }

    /** Verify / reject is Super Admin only. */
    public function review(User $user, TaxKycProfile $profile): bool
    {
        return $user->hasRole('super-admin');
    }
}
