<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Club discussion post (Phase 4 spec §6.2, §12.1.15).
 * All Phase 4 posts live inside a club. No pre-moderation; a reported
 * post is hidden by Support (`status=hidden`) and may be restored /
 * deleted by Admin.
 */
class CommunityPost extends Model
{
    public const TYPE_DISCUSSION = 'discussion';

    public const TYPE_REVIEW_SHARE = 'review_share';

    public const TYPE_ACTIVITY = 'activity';

    public const TYPES = [self::TYPE_DISCUSSION, self::TYPE_REVIEW_SHARE, self::TYPE_ACTIVITY];

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = ['club_id', 'user_id', 'type', 'body', 'book_id', 'status'];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }
}
