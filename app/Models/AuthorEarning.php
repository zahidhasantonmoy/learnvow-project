<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorEarning extends Model
{
    public const TYPE_SALE = 'sale';

    public const TYPE_POOL = 'pool';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'author_id', 'book_id', 'order_id', 'period', 'type',
        'amount_bdt', 'share_pct', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount_bdt' => 'integer',
            'share_pct' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
