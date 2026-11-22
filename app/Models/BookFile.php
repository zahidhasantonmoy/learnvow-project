<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookFile extends Model
{
    protected $fillable = ['book_id', 'type', 'disk', 'path', 'size_bytes', 'mime'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
