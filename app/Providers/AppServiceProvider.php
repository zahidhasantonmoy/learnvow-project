<?php

namespace App\Providers;

use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\ReaderSetting;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Policies\ReadingPolicy;
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

        // Phase 2: one ReadingPolicy covers five models, so Laravel's
        // name-guess cannot find it — register it explicitly (spec §1.4).
        Gate::policy(Bookmark::class, ReadingPolicy::class);
        Gate::policy(Highlight::class, ReadingPolicy::class);
        Gate::policy(Note::class, ReadingPolicy::class);
        Gate::policy(ReadingProgress::class, ReadingPolicy::class);
        Gate::policy(ReaderSetting::class, ReadingPolicy::class);
    }
}
