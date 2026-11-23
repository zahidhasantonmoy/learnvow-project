<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Builder C — Phase 3 spec §7.15. Per-user notification preferences
 * (one row per user). The notifications themselves live in Laravel's
 * standard `notifications` table (database channel only, A13).
 */
class NotificationSetting extends Model
{
    protected $fillable = ['user_id', 'payouts', 'approvals', 'tickets', 'announcements'];

    protected function casts(): array
    {
        return [
            'payouts' => 'boolean',
            'approvals' => 'boolean',
            'tickets' => 'boolean',
            'announcements' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Preferences for a user, creating the all-on defaults if missing. */
    public static function forUser(User $user): static
    {
        return static::firstOrCreate(['user_id' => $user->getKey()]);
    }
}
