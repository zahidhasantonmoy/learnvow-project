<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = ['referrer_id', 'referee_id', 'code_used', 'status', 'rewarded_at'];

    protected function casts(): array
    {
        return [
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsto(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsto(User::class, 'referee_id');
    }
}
