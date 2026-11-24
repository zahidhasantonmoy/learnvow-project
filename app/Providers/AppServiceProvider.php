<?php

namespace App\Providers;

use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\ReaderSetting;
use App\Models\ReadingGoal;
use App\Models\ReadingProgress;
use App\Models\SupportTicket;
use App\Models\TakedownRequest;
use App\Models\TaxKycProfile;
use App\Models\User;
use App\Models\VocabularyWord;
use App\Policies\ApiTokenPolicy;
use App\Policies\EarningPolicy;
use App\Policies\GoalPolicy;
use App\Policies\PayoutPolicy;
use App\Policies\ReadingPolicy;
use App\Policies\TakedownPolicy;
use App\Policies\TaxKycPolicy;
use App\Policies\TicketPolicy;
use App\Policies\VocabularyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

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

        // Phase 3 (Builder C): these policy names do not match Laravel's
        // model-name guess, so register them explicitly.
        Gate::policy(SupportTicket::class, TicketPolicy::class);
        Gate::policy(TakedownRequest::class, TakedownPolicy::class);

        // Phase 4 (Builder C): TaxKycPolicy does not match Laravel's
        // guess for the TaxKycProfile model — register explicitly.
        Gate::policy(TaxKycProfile::class, TaxKycPolicy::class);

        // Phase 4 (Builder A): GoalPolicy/VocabularyPolicy do not match
        // Laravel's name-guess for ReadingGoal/VocabularyWord — register
        // explicitly. ApiTokenPolicy (Builder B) guards Sanctum tokens.
        Gate::policy(ReadingGoal::class, GoalPolicy::class);
        Gate::policy(VocabularyWord::class, VocabularyPolicy::class);
        Gate::policy(PersonalAccessToken::class, ApiTokenPolicy::class);

        // Phase 2: one ReadingPolicy covers five models, so Laravel's
        // name-guess cannot find it — register it explicitly (spec §1.4).
        Gate::policy(Bookmark::class, ReadingPolicy::class);
        Gate::policy(Highlight::class, ReadingPolicy::class);
        Gate::policy(Note::class, ReadingPolicy::class);
        Gate::policy(ReadingProgress::class, ReadingPolicy::class);
        Gate::policy(ReaderSetting::class, ReadingPolicy::class);

        // Phase 3 (Builder B): EarningPolicy/PayoutPolicy do not match
        // Laravel's name-guess, so register them explicitly.
        Gate::policy(AuthorEarning::class, EarningPolicy::class);
        Gate::policy(AuthorPayout::class, PayoutPolicy::class);
    }
}
