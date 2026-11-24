<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One episode of a serial (§4.7). Price column exists; unused in Phase 4 (A7). */
class SerialEpisode extends Model
{
    protected $fillable = [
        'serial_id', 'episode_no', 'title', 'content',
        'published_at', 'is_free', 'price_bdt',
    ];

    protected function casts(): array
    {
        return ['episode_no' => 'integer', 'published_at' => 'datetime', 'is_free' => 'boolean', 'price_bdt' => 'integer'];
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(Serial::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }
}
