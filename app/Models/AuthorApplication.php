<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Phase 3 (spec §7.1) — Builder A. Author application (becomeAuthor). */
class AuthorApplication extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Statuses that count as one active application (spec §7.1 note). */
    public const ACTIVE_STATUSES = [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW];

    protected $fillable = [
        'user_id', 'author_id', 'pen_name', 'bio', 'bio_en',
        'bkash_number', 'id_document_path', 'status',
        'reviewed_by', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'bkash_number' => 'encrypted',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
