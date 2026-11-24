<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reader's request for a book LearnVow does not have (§4.9). Max 5/month. */
class BookRequest extends Model
{
    public const MAX_PER_MONTH = 5;

    protected $fillable = [
        'user_id', 'title', 'author_name', 'reason',
        'status', 'reviewed_by', 'review_note',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
