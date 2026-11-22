<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Book extends Model
{
    use Searchable;

    protected $fillable = [
        'author_id', 'category_id', 'title', 'title_en', 'slug',
        'description', 'description_en', 'language', 'price_bdt',
        'list_price_bdt', 'isbn', 'pages', 'has_audiobook', 'is_free',
        'is_public_domain', 'is_subscription_included', 'sample_percent',
        'status', 'cover_c1', 'cover_c2', 'cover_image_path', 'file_path',
        'rights_territories', 'rating_avg', 'ratings_count', 'sales_count',
        'readers_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_bdt' => 'integer',
            'list_price_bdt' => 'integer',
            'pages' => 'integer',
            'has_audiobook' => 'boolean',
            'is_free' => 'boolean',
            'is_public_domain' => 'boolean',
            'is_subscription_included' => 'boolean',
            'rights_territories' => 'array',
            'rating_avg' => 'float',
            'ratings_count' => 'integer',
            'sales_count' => 'integer',
            'readers_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'rights_territories' => '["BD"]',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'book_tag');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Scout (database driver, spec A9): searchable text columns only. */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'title_en' => $this->title_en,
            'description' => $this->description,
            'description_en' => $this->description_en,
        ];
    }

    /** Recompute cached aggregates from real approved reviews only (A8). */
    public function refreshRatingAggregates(): void
    {
        $approved = $this->reviews()->where('status', 'approved');
        $this->ratings_count = (clone $approved)->count();
        $this->rating_avg = $this->ratings_count > 0
            ? round((clone $approved)->avg('rating'), 1)
            : 0;
        $this->save();
    }
}
