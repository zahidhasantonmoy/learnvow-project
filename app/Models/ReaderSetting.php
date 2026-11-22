<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderSetting extends Model
{
    protected $table = 'reader_settings';

    protected $fillable = [
        'user_id', 'book_id', 'font_family', 'font_size', 'line_height',
        'theme', 'brightness', 'reading_mode',
    ];

    protected function casts(): array
    {
        return [
            'font_size' => 'integer',
            'line_height' => 'float',
            'brightness' => 'integer',
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
}
