<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function view(User $user, User $target): bool
    {
        // ADM-02: an Admin may never open the Super Admin's record —
        // only the Super Admin themself (or another Super Admin) may.
        if ($target->hasRole('super-admin') && ! $user->hasRole('super-admin')) {
            return $user->id === $target->id;
        }

        return $user->id === $target->id || $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Manage users (list, edit details, change roles other than
     * super-admin) = admin / super-admin.
     */
    public function manage(User $user, User $target): bool
    {
        // ADM-02: see view() — the Super Admin's record is off-limits
        // to plain Admins.
        if ($target->hasRole('super-admin') && ! $user->hasRole('super-admin')) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function update(User $user, User $target): bool
    {
        // ADM-02: an Admin may never edit (or save) the Super Admin's
        // record — only the Super Admin themself may.
        if ($target->hasRole('super-admin') && ! $user->hasRole('super-admin')) {
            return $user->id === $target->id;
        }

        return $user->id === $target->id || $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Suspend / ban = super-admin ONLY (roles doc: only the owner).
     */
    public function suspend(User $user, User $target): bool
    {
        return $user->hasRole('super-admin') && ! $target->hasRole('super-admin');
    }

    public function ban(User $user, User $target): bool
    {
        return $user->hasRole('super-admin') && ! $target->hasRole('super-admin');
    }

    /**
     * Manage roles = super-admin ONLY. Admins may approve books/authors
     * but may never create/remove admins or change roles.
     */
    public function manageRoles(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    /**
     * Delete an account = super-admin ONLY, and the Super Admin account
     * itself can never be deleted.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasRole('super-admin') && ! $target->hasRole('super-admin');
    }
}
