<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['order_no', 'user_id', 'type', 'status', 'subtotal_bdt', 'coupon_id', 'coupon_amt_bdt', 'wallet_used_bdt', 'total_bdt', 'refund_requested_at', 'refund_status'];

    protected function casts(): array
    {
        return [
            'subtotal_bdt' => 'integer',
            'coupon_amt_bdt' => 'integer',
            'wallet_used_bdt' => 'integer',
            'total_bdt' => 'integer',
            'refund_requested_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsto(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasmany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasmany(Payment::class);
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasmany(PaymentEvent::class);
    }
}
