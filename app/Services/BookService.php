<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Book catalogue logic (spec §6). Implemented by Builder B.
 * Controllers and Livewire components must use this service —
 * never put business logic in controllers.
 */
class BookService
{
    /**
     * Transliteration pairs so a Bengali query finds an English-titled
     * book and vice versa (e.g. "gitanjali" and "গীতাঞ্জলি" both match
     * Gitanjali, whose title column holds only the Latin form).
     */
    protected const array TRANSLITERATIONS = [
        'gitanjali' => 'গীতাঞ্জলি',
        'গীতাঞ্জলি' => 'gitanjali',
        'the prophet' => 'প্রফেট',
        'prophet' => 'প্রফেট',
        'প্রফেট' => 'prophet',
        'pride and prejudice' => 'প্রাইড অ্যান্ড প্রেজুডিস',
        'meditations' => 'মেডিটেশনস',
        'মেডিটেশনস' => 'meditations',
        'prompt engineering basics' => 'প্রম্পট ইঞ্জিনিয়ারিং',
    ];

    /** Paginated published-book list with filters (spec §3.1, §5 BookList). */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Book::published()->with(['author', 'category']);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['category_slug'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category_slug']));
        }

        if (($filters['price'] ?? null) === 'free') {
            $query->where('is_free', true);
        } elseif (($filters['price'] ?? null) === 'paid') {
            $query->where('is_free', false);
        }

        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        match ($filters['sort'] ?? 'newest') {
            'popular' => $query->orderByDesc('sales_count')->orderByDesc('ratings_count'),
            'price_asc' => $query->orderBy('price_bdt')->orderBy('title'),
            'price_desc' => $query->orderByDesc('price_bdt')->orderBy('title'),
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('ratings_count'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->paginate($filters['per_page'] ?? 12)->withQueryString();
    }

    /**
     * Search published books. Scout (database driver, A9) first, then a
     * LIKE fallback across titles/descriptions/author name, plus the
     * transliteration map — so Bengali and transliterated queries match.
     */
    public function search(string $query, array $filters = []): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return new Collection();
        }

        $terms = array_values(array_unique(array_filter([
            $query,
            self::TRANSLITERATIONS[mb_strtolower($query)] ?? null,
        ])));

        $found = collect();

        // 1) Scout (database driver — LIKE over toSearchableArray columns).
        try {
            $scout = Book::search($query)
                ->query(fn ($q) => $q->where('status', 'published'))
                ->get();
            $found = $found->merge($scout);
        } catch (\Throwable) {
            // Scout misconfigured — the LIKE fallback below still answers.
        }

        // 2) LIKE fallback (SQLite dev) + author-name match + transliterations.
        $like = Book::published()->with(['author', 'category'])
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $wild = '%'.$term.'%';
                    $q->orWhere('title', 'like', $wild)
                        ->orWhere('title_en', 'like', $wild)
                        ->orWhere('description', 'like', $wild)
                        ->orWhere('description_en', 'like', $wild)
                        ->orWhereHas('author', fn ($aq) => $aq->where('name', 'like', $wild));
                }
            })
            ->orderByDesc('ratings_count')
            ->limit(50)
            ->get();

        $found = $found->merge($like)->unique('id')->values();

        // Apply the same list filters, if any.
        if (! empty($filters['category_slug'])) {
            $found = $found->where('category.slug', $filters['category_slug'])->values();
        }
        if (($filters['price'] ?? null) === 'free') {
            $found = $found->where('is_free', true)->values();
        } elseif (($filters['price'] ?? null) === 'paid') {
            $found = $found->where('is_free', false)->values();
        }
        if (! empty($filters['language'])) {
            $found = $found->where('language', $filters['language'])->values();
        }

        return new Collection($found->values()->all());
    }

    /** Published book by slug, with relations — 404 when missing/unpublished. */
    public function show(string $slug): Book
    {
        return Book::published()
            ->with(['author', 'category', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * May this user read the full book? Phase 2 rules (spec §2.3, A19):
     *  1. A bought/gifted/free entitlement (user_books) always reads —
     *     even when the book is taken_down (old buyers keep access).
     *  2. A published free book reads for any logged-in user.
     *  3. Premium (trialing/active, or cancelled but still in term,
     *     and not expired) reads a published book
     *     whose is_subscription_included is true.
     *  4. Otherwise no (checkout). Premium does NOT open a taken_down
     *     book — only prior buyers keep that access (roles doc edge 5;
     *     the spec leaves Premium-on-taken_down open, this is the call).
     * The Subscription model/table may not exist yet while Builder B
     * works — guard so canRead stays safe until then.
     */
    public function canRead(?User $user, Book $book): bool
    {
        if ($user === null) {
            return false;
        }

        if ($book->userBooks()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($book->status !== 'published') {
            return false;
        }

        if ($book->is_free) {
            return true;
        }

        if (! $book->is_subscription_included) {
            return false;
        }

        return $this->hasActivePremium($user);
    }

    /** Premium trialing/active and not past its end (spec §2.2). */
    public function hasActivePremium(User $user): bool
    {
        if (! class_exists(\App\Models\Subscription::class)
            || ! Schema::hasTable('subscriptions')) {
            return false;
        }

        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->whereIn('status', ['trialing', 'active', 'cancelled'])
            ->orderByDesc('ends_at')
            ->first();

        if ($subscription === null) {
            return false;
        }

        // Cancelled runs to end of term (§2.2) — no refund, access stays.
        $end = $subscription->status === 'trialing'
            ? ($subscription->trial_ends_at ?? $subscription->ends_at)
            : $subscription->ends_at;

        return $end !== null && $end->isFuture();
    }

    /** Book JSON-LD (spec §3.1): real fields only — no fake ISBN/rating. */
    public function bookJsonLd(Book $book): array
    {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => $book->title,
            'author' => [
                '@type' => 'Person',
                'name' => $book->author->name ?? '',
            ],
            'bookFormat' => 'EBook',
            'inLanguage' => $book->language,
            'numberOfPages' => $book->pages,
            'offers' => [
                '@type' => 'Offer',
                'price' => $book->price_bdt,
                'priceCurrency' => 'BDT',
                'availability' => 'https://schema.org/InStock',
            ],
        ];

        if ($book->isbn) {
            $jsonLd['isbn'] = $book->isbn;
        }
        if ($book->published_at) {
            $jsonLd['datePublished'] = $book->published_at->toDateString();
        }
        // AggregateRating ONLY from real reviews (A8): seeded aggregates are
        // demo numbers, so omit them — no fake review rows exist.
        if ($book->reviews()->where('status', 'approved')->exists()) {
            $jsonLd['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $book->rating_avg,
                'ratingCount' => $book->ratings_count,
            ];
        }

        return $jsonLd;
    }

    /** Person JSON-LD for an author page. */
    public function authorJsonLd(\App\Models\Author $author): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $author->name,
            'description' => app()->getLocale() === 'bn'
                ? ($author->bio ?? $author->bio_en)
                : ($author->bio_en ?? $author->bio),
        ];
    }
}
