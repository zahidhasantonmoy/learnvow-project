<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One taste profile per user (§4.10); feeds forYou recommendations. */
class TasteProfile extends Model
{
    protected $fillable = ['user_id', 'genres', 'authors', 'languages', 'reading_time'];

    protected function casts(): array
    {
        return ['genres' => 'array', 'authors' => 'array', 'languages' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Category ids the user picked (empty array when none). */
    public function genreIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) ($this->genres ?? []))));
    }

    /** Author ids the user picked (empty array when none). */
    public function authorIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) ($this->authors ?? []))));
    }
}
