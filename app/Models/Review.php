<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'book_id', 'rating', 'title', 'body', 'status', 'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    protected static function booted(): void
    {
        // When an approved review appears, refresh the book's cached aggregates (A8).
        static::saved(function (Review $review) {
            if ($review->status === 'approved') {
                $review->book?->refreshRatingAggregates();
            }
        });
    }
}
