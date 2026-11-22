<?php

namespace App\Http\Middleware;

use App\Services\AdminService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builder C — Filament panel guard (spec A15).
 *
 * Filament's own Authenticate middleware only calls canAccessPanel() when
 * the User model implements FilamentUser; without it, any logged-in user
 * may enter the panel in a local environment. This middleware closes that
 * gap: only users with the `admin` or `super-admin` role may use /admin,
 * whatever the User model implements. Role checks read the Spatie pivot
 * tables, so they work before Builder A adds the HasRoles trait.
 */
class EnsureAdminPanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(AdminService::canAccessAdminPanel($user), 403);

        return $next($request);
    }
}
