<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name', 'name_en', 'slug', 'description', 'description_en',
        'parent_id', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function publishedBooks(): HasMany
    {
        return $this->books()->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' ? $this->name : $this->name_en;
    }

    public function displayDescription(): ?string
    {
        return app()->getLocale() === 'bn' ? ($this->description ?? $this->description_en) : ($this->description_en ?? $this->description);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
