<?php

namespace App\Filament\Resources;

use App\Services\AdminService;
use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — shared authorization for every admin resource (spec A15).
 *
 * Filament delegates resource authorization to model policies, and Builder
 * A has not landed the policies yet — without an override, every panel
 * user could use every resource. These overrides deny by role until the
 * policies exist, and stay in force afterwards as a second layer.
 * RoleResource sets $superAdminOnly = true (Super Admin only, roles doc).
 */
abstract class BaseAdminResource extends Resource
{
    protected static bool $superAdminOnly = false;

    protected static function currentUser(): ?\App\Models\User
    {
        $user = auth()->user();

        return $user instanceof \App\Models\User ? $user : null;
    }

    protected static function resourceAllowed(): bool
    {
        $user = static::currentUser();

        return static::$superAdminOnly
            ? AdminService::isSuperAdmin($user)
            : AdminService::isAdmin($user);
    }

    protected static function allowOrDeny(bool $allowed): Response
    {
        return $allowed ? Response::allow() : Response::deny();
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::allowOrDeny(static::resourceAllowed());
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::allowOrDeny(static::resourceAllowed());
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::allowOrDeny(static::resourceAllowed());
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::allowOrDeny(static::resourceAllowed());
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        // Roles doc: only Super Admin may delete a book (and, by the same
        // rule applied here, any other admin-managed record). Admin can
        // approve/reject/hide, never delete.
        return static::allowOrDeny(AdminService::isSuperAdmin(static::currentUser()));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::allowOrDeny(AdminService::isSuperAdmin(static::currentUser()));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$superAdminOnly
            ? __('admin.nav.super_admin')
            : __('admin.nav.manage');
    }
}
