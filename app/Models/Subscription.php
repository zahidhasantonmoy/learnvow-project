<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'plan_id', 'order_id', 'status', 'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at', 'cancel_reason', 'past_due_at', 'gateway'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            // Phase 4 (§12.2).
            'past_due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsto(SubscriptionPlan::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }
}
