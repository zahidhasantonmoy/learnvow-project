<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Licensed PDF download log (Phase 4 spec §2.4, §12.1.5).
 * Separate from the Phase 2 `downloads` table (EPUB downloads).
 * One row per download; the same book may be downloaded repeatedly.
 */
class PdfDownload extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'book_id', 'file_id', 'watermark_text', 'ip_address', 'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
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

    public function file(): BelongsTo
    {
        return $this->belongsTo(BookFile::class, 'file_id');
    }
}
