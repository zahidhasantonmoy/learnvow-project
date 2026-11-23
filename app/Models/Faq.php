<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — Phase 3 spec §7.13. FAQ entry (bn + en). No rows are
 * seeded (§8.4) — Support / Super Admin write them.
 */
class Faq extends Model
{
    protected $fillable = [
        'question', 'question_en', 'answer', 'answer_en',
        'category', 'sort_order', 'is_active',
    ];

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
