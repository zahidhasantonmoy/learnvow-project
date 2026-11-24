<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Reader shelf (§4.2). One user, max 20 shelves; unique name per user. */
class Collection extends Model
{
    public const MAX_PER_USER = 20;

    public const MAX_BOOKS = 200;

    protected $fillable = [
        'user_id', 'name', 'name_en', 'description', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'sort_order' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'collection_items')
            ->withPivot(['sort_order', 'added_at'])
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    public function hasBook(Book $book): bool
    {
        return $this->items()->where('book_id', $book->id)->exists();
    }
}
