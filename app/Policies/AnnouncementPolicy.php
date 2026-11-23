<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §3.4, §12. Announcements.
 * Admin / Super Admin manage; readers only ever read them on the site.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasRole('super-admin');
    }
}
