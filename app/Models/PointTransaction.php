<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointTransaction extends Model
{
    public const UPDATED_AT = null; // created_at only (spec §6.13/§6.14/§6.18)
    protected $fillable = ['user_id', 'type', 'points', 'balance_after', 'reference_type', 'reference_id'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'reference_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsto(User::class);
    }
}
