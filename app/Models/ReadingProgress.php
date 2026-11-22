<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProgress extends Model
{
    protected $table = 'reading_progress';

    protected $fillable = [
        'user_id', 'book_id', 'cfi', 'percent', 'chapter_index',
        'total_minutes', 'finished_at', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'chapter_index' => 'integer',
            'total_minutes' => 'integer',
            'finished_at' => 'datetime',
            'last_read_at' => 'datetime',
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

    public function isFinished(): bool
    {
        return $this->finished_at !== null || $this->percent >= 100;
    }
}
