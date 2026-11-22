<?php

namespace App\Http\Middleware;

use App\Services\AdminService;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

/**
 * Builder C — replaces Filament's Authenticate middleware for the admin
 * panel (spec A15). Filament's version only calls canAccessPanel() when
 * the User model implements FilamentUser — and 403s everyone in
 * non-local environments when it does not. This version enforces the
 * same rule through AdminService (admin / super-admin roles, read from
 * the Spatie pivot tables), so panel access never depends on Builder A
 * having added FilamentUser to the User model. A should still add
 * FilamentUser::canAccessPanel() to User for defence in depth.
 */
class AuthenticateAdminPanel extends FilamentAuthenticate
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        $user = $guard->user();

        abort_unless(AdminService::canAccessAdminPanel($user), 403);
    }
}
