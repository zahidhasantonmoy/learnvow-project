<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Roles & permissions are managed by the Super Admin only (spec §1.2).
        // Policies are auto-discovered by Laravel; this gate is the single
        // check Filament's RoleResource and other role screens use.
        Gate::define('roles.manage', fn (User $user) => $user->hasRole('super-admin'));
    }
}
