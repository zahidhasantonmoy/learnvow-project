<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletTransaction extends Model
{
    public const UPDATED_AT = null; // created_at only (spec §6.13/§6.14/§6.18)
    protected $fillable = ['user_id', 'type', 'amount_bdt', 'balance_after_bdt', 'reference_type', 'reference_id', 'note'];

    protected function casts(): array
    {
        return [
            'amount_bdt' => 'integer',
            'balance_after_bdt' => 'integer',
            'reference_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }
}
