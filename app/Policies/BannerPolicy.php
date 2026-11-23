<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §3.3, §12. Home banners.
 * Admin / Super Admin manage; only Super Admin deletes.
 */
class BannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->hasRole('super-admin');
    }
}
