<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingMinute extends Model
{
    protected $fillable = [
        'user_id', 'book_id', 'author_id', 'period',
        'minutes', 'counted', 'flagged', 'flag_note',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'counted' => 'boolean',
            'flagged' => 'boolean',
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
