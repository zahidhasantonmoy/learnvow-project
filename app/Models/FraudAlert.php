<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FraudAlert extends Model
{
    protected $fillable = ['trx_id_normalized', 'payment_id', 'existing_payment_id', 'status', 'decided_by', 'decision_note'];

    public function payment(): BelongsTo
    {
        return $this->belongsto(Payment::class);
    }

    public function existingPayment(): BelongsTo
    {
        return $this->belongsto(Payment::class, 'existing_payment_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsto(User::class, 'decided_by');
    }
}
