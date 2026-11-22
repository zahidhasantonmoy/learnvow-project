<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentEvent extends Model
{
    public const UPDATED_AT = null; // append-only log: created_at only, never updated
    protected $fillable = ['payment_id', 'order_id', 'actor_id', 'action', 'note'];

    public function payment(): BelongsTo
    {
        return $this->belongsto(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsto(User::class, 'actor_id');
    }
}
