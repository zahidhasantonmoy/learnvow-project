<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Badge definition (§4.5, §13.2). Seeded only — never awarded directly. */
class Badge extends Model
{
    public $timestamps = false;

    protected $fillable = ['slug', 'name', 'name_en', 'description', 'description_en', 'icon', 'rule_key'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }
}
