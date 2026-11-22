<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCode extends Model
{
    protected $fillable = ['code', 'book_id', 'buyer_id', 'order_id', 'claimed_by', 'status', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsto(Book::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsto(User::class, 'buyer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsto(User::class, 'claimed_by');
    }
}
