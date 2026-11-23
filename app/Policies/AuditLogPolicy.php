<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §4.5, §0.8. The audit log is append-only:
 * Super Admin may READ it; nobody may create (except the service),
 * update or delete a row — not even Super Admin.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
