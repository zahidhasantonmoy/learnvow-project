<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcmToken extends Model
{
    protected $fillable = ['user_id', 'device_id', 'token', 'platform'];

    protected $hidden = ['token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** Last 6 chars only — a full FCM token is never shown/logged (spec §8.2). */
    public function maskedToken(): string
    {
        return '…'.substr((string) $this->token, -6);
    }
}
