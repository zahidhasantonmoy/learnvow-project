<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — Phase 3 spec §7.12. Home banner (public image, plain
 * Storage — a banner is not a book file). No rows are seeded (§8.4).
 */
class Banner extends Model
{
    protected $fillable = ['title', 'image_path', 'link_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
