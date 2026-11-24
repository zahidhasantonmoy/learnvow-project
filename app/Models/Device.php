<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;

class Device extends Model
{
    protected $fillable = [
        'user_id', 'device_name', 'device_type', 'platform',
        'last_ip', 'user_agent', 'last_active_at', 'api_token_id',
    ];

    protected function casts(): array
    {
        return ['last_active_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'api_token_id');
    }

    /** Fingerprint for a web device: User-Agent + IP hash (spec §5.1, A10). */
    public static function webFingerprint(?string $userAgent, ?string $ip): string
    {
        return hash('sha256', ($userAgent ?? '').'|'.($ip ?? ''));
    }
}
