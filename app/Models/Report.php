<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Builder C — Phase 3 spec §7.8. Report against a book or a review.
 * reportable_type holds the plain table name ('books' / 'reviews').
 */
class Report extends Model
{
    public const TYPE_BOOK = 'books';

    public const TYPE_REVIEW = 'reviews';

    public const TYPE_POST = 'community_posts';

    protected $fillable = [
        'reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details',
        'status', 'reviewed_by', 'review_note',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The reported book, when reportable_type is 'books'. */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'reportable_id');
    }

    /** The reported review, when reportable_type is 'reviews'. */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class, 'reportable_id');
    }

    /** The reported club post, when reportable_type is 'community_posts'. */
    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'reportable_id');
    }

    /** The reported record (Book, Review or club post), or null. */
    public function reportable(): ?Model
    {
        return match ($this->reportable_type) {
            self::TYPE_BOOK => $this->book,
            self::TYPE_REVIEW => $this->review,
            self::TYPE_POST => $this->post,
            default => null,
        };
    }
}
