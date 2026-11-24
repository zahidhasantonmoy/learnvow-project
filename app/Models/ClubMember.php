<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubMember extends Model
{
    public $timestamps = false;

    public const ROLE_MEMBER = 'member';

    public const ROLE_MODERATOR = 'moderator';

    public const ROLE_CREATOR = 'creator';

    protected $fillable = ['club_id', 'user_id', 'role', 'joined_at'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isModerator(): bool
    {
        return in_array($this->role, [self::ROLE_MODERATOR, self::ROLE_CREATOR], true);
    }
}
