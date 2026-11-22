<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the 6 Spatie roles (no Guest row — Guest is simply "not logged in")
 * and the 10 Phase 1 permissions (spec §1.2).
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permission => roles that hold it.
     *
     * @var array<string, array<int, string>>
     */
    public const PERMISSION_ROLES = [
        'books.view' => ['reader', 'author', 'admin', 'super-admin', 'support'],
        'books.approve' => ['admin', 'super-admin'],
        'books.reject' => ['admin', 'super-admin'],
        'authors.approve' => ['admin', 'super-admin'],
        'authors.reject' => ['admin', 'super-admin'],
        'users.manage' => ['admin', 'super-admin'],
        'users.suspend' => ['admin', 'super-admin'],
        'categories.manage' => ['admin', 'super-admin'],
        'reviews.moderate' => ['admin', 'super-admin', 'support'],
        // roles.manage is deliberately super-admin ONLY (roles doc: only the
        // owner creates/removes admins and manages roles).
        'roles.manage' => ['super-admin'],
    ];

    public function run(): void
    {
        // Reset cached roles/permissions so seeding is idempotent.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = ['reader', 'author', 'admin', 'super-admin', 'support', 'print-partner'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach (self::PERMISSION_ROLES as $permission => $roleNames) {
            $perm = Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $perm->syncRoles($roleNames);
        }
    }
}
