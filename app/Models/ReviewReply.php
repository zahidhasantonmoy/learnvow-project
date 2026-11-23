<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3 (spec §7.5) — Builder A. One public author reply per review.
 * Editable by the author; deletable only by Admin/Super Admin
 * (ReviewReplyPolicy).
 */
class ReviewReply extends Model
{
    protected $fillable = ['review_id', 'author_id', 'user_id', 'body'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
