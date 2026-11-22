<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = ['order_id', 'user_id', 'method', 'trx_id', 'trx_id_normalized', 'amount_bdt', 'status', 'submitted_at', 'verified_at', 'verified_by', 'fix_count', 'admin_note'];

    protected function casts(): array
    {
        return [
            'amount_bdt' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'fix_count' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsto(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsto(User::class, 'verified_by');
    }

    public function events(): HasMany
    {
        return $this->hasmany(PaymentEvent::class);
    }

    public function fraudAlerts(): HasMany
    {
        return $this->hasmany(FraudAlert::class);
    }
}
