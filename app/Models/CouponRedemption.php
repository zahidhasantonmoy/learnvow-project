<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponRedemption extends Model
{
    public const UPDATED_AT = null; // created_at only (spec §6.13/§6.14/§6.18)
    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_bdt'];

    protected function casts(): array
    {
        return [
            'discount_bdt' => 'integer',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsto(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }
}
