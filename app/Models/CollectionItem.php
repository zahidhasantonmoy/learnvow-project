<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One book on one shelf (§4.2). A book may sit on many shelves. */
class CollectionItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['collection_id', 'book_id', 'sort_order', 'added_at'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'added_at' => 'datetime'];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
