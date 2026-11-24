<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-order = reservation only, no money (§4.8, A8). One active
 * (reserved/notified) preorder per user+book — enforced in the
 * controller, since the spec forbids a partial unique index here.
 */
class Preorder extends Model
{
    public const ACTIVE_STATUSES = ['reserved', 'notified'];

    protected $fillable = ['user_id', 'book_id', 'status', 'notified_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
