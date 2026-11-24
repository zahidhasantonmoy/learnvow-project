<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serial story (§4.7). Approval is recorded in approved_at/approved_by
 * (AdminService::approveSerial) — the spec's status enum has no approval
 * state, so approval is a separate marker, not a status value.
 */
class Serial extends Model
{
    public const MAX_EPISODES = 100;

    protected $fillable = [
        'author_id', 'title', 'title_en', 'description', 'cover_image_path',
        'status', 'is_free', 'is_subscription_included', 'price_bdt',
        'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_subscription_included' => 'boolean',
            'price_bdt' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(SerialEpisode::class)->orderBy('episode_no');
    }

    public function publishedEpisodes(): HasMany
    {
        return $this->episodes()->whereNotNull('published_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
