<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['slug', 'name', 'name_en', 'price_bdt', 'duration_days', 'trial_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'price_bdt' => 'integer',
            'duration_days' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasmany(Subscription::class);
    }
}
