<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    protected $fillable = [
        'user_id', 'name', 'pen_name', 'slug', 'bio', 'bio_en', 'type',
        'revenue_share_pct', 'bkash_number', 'status',
    ];

    protected function casts(): array
    {
        return [
            'bkash_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function publishedBooks(): HasMany
    {
        return $this->books()->where('status', 'published');
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
