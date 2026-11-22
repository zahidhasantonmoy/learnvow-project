<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'book_id', 'plan_id', 'title_snapshot', 'unit_price_bdt', 'list_price_bdt', 'quantity'];

    protected function casts(): array
    {
        return [
            'unit_price_bdt' => 'integer',
            'list_price_bdt' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsto(Book::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsto(SubscriptionPlan::class);
    }
}
