<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saved dictionary word (§4.11). One row per user+word (stored lowercase). */
class VocabularyWord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'word', 'meaning_en', 'meaning_bn', 'example', 'book_id', 'cfi',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
