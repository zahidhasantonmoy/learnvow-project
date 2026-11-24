<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'email', 'password', 'locale', 'country', 'status', 'wallet_balance_bdt', 'points_balance', 'trial_used_at', 'referral_code', 'reader_default_font', 'reader_default_size'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, HasRoles, Notifiable;

    /**
     * Defence in depth (with Builder C's EnsureAdminPanelAccess middleware):
     * only staff roles may enter the Filament admin panel. Readers and
     * authors get a 403 from Filament's stock Authenticate middleware in
     * non-local envs without this.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'super-admin', 'support']);
    }

    /**
     * Account statuses (roles doc: Status = account state).
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_BANNED = 'banned';

    public const STATUS_PENDING_APPROVAL = 'pending-approval';

    /**
     * Statuses that may not log in.
     */
    public static function blockedStatuses(): array
    {
        return [self::STATUS_SUSPENDED, self::STATUS_BANNED];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            // Phase 2 (spec §6.22).
            'trial_used_at' => 'datetime',
            'wallet_balance_bdt' => 'integer',
            'points_balance' => 'integer',
            'reader_default_size' => 'integer',
        ];
    }

    /**
     * Phase 4 (spec §5.1) — registered devices (web + app).
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Phase 4 (spec §8.2) — FCM push tokens.
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }
}
