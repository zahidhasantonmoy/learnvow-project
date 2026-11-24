<?php

namespace App\Services;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Models\TasteProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rule-based "For You" recommendations (§4.3). No AI call — the
 * rules run in this order and each hit carries its reason:
 *  (a) same category as a finished book,
 *  (b) favourite author's new books (taste profile),
 *  (c) most-read books in a category the user reads a lot,
 *  (d) newly published (last 7 days),
 *  fallback: most-sold books.
 * Only published, unpaused, unhidden books; never the user's own
 * books and never books already read/finished.
 */
class RecommendationService
{
    public function __construct(protected BookService $books) {}

    /**
     * @return array<int, array{book: Book, reason: string}>
     */
    public function forUser(User $user, int $limit = 20): array
    {
        $results = [];
        $seen = [];

        $add = function (Book $book, string $reason) use (&$results, &$seen, $limit): void {
            if (count($results) >= $limit || isset($seen[$book->id])) {
                return;
            }
            $seen[$book->id] = true;
            $results[] = ['book' => $book, 'reason' => $reason];
        };

        // PERF-04: cap the finished history — the category/author rules
        // only need a recent slice, not the user's entire lifetime.
        $finished = ReadingProgress::where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->with('book')
            ->orderByDesc('finished_at')
            ->limit(100)
            ->get();

        $finishedCategoryIds = $finished->map(fn ($p) => $p->book?->category_id)
            ->filter()->unique()->values()->all();

        $finishedCategoryNames = [];
        if ($finishedCategoryIds !== []) {
            $nameColumn = app()->getLocale() === 'bn' ? 'name' : 'name_en';
            $finishedCategoryNames = DB::table('categories')
                ->whereIn('id', $finishedCategoryIds)->pluck($nameColumn, 'id')->all();
            // Fall back to the Bengali name where no English name exists.
            $bnNames = DB::table('categories')
                ->whereIn('id', $finishedCategoryIds)->pluck('name', 'id')->all();
            foreach ($finishedCategoryNames as $id => $name) {
                if ($name === null || $name === '') {
                    $finishedCategoryNames[$id] = $bnNames[$id] ?? '';
                }
            }
        }

        // (a) Same category as a finished book.
        foreach ($finishedCategoryIds as $categoryId) {
            foreach ($this->candidates($user)->where('category_id', $categoryId)->orderByDesc('ratings_count')->limit(5)->get() as $book) {
                $name = $finishedCategoryNames[$categoryId] ?? '';
                $add($book, __('books.for_you_reason_category', ['category' => $name]));
            }
            if (count($results) >= $limit) {
                return $results;
            }
        }

        // (b) Favourite authors from the taste profile.
        $taste = TasteProfile::where('user_id', $user->id)->first();
        if ($taste !== null && $taste->authorIds() !== []) {
            foreach ($this->candidates($user)->whereIn('author_id', $taste->authorIds())->orderByDesc('published_at')->limit(5)->get() as $book) {
                $add($book, __('books.for_you_reason_author', ['author' => $book->author->name ?? '']));
            }
            if (count($results) >= $limit) {
                return $results;
            }
        }

        // (c) Most-read books in a category the user reads a lot.
        if ($finishedCategoryIds !== []) {
            foreach ($this->candidates($user)->whereIn('category_id', $finishedCategoryIds)->orderByDesc('readers_count')->limit(5)->get() as $book) {
                $add($book, __('books.for_you_reason_popular_category'));
            }
            if (count($results) >= $limit) {
                return $results;
            }
        }

        // (d) Newly published (last 7 days).
        foreach ($this->candidates($user)->where('published_at', '>=', now()->subDays(7))->orderByDesc('published_at')->limit(5)->get() as $book) {
            $add($book, __('books.for_you_reason_new'));
        }
        if (count($results) >= $limit) {
            return $results;
        }

        // Fallback: most-sold books (real sales, never fake).
        foreach ($this->candidates($user)->orderByDesc('sales_count')->orderByDesc('ratings_count')->limit($limit)->get() as $book) {
            $add($book, __('books.for_you_reason_popular'));
        }

        return $results;
    }

    /** Base candidate query (§4.3 limits): published, visible, not own, not read. */
    public function candidates(User $user)
    {
        $ownAuthorIds = DB::table('authors')->where('user_id', $user->id)->pluck('id')->all();

        $readBookIds = ReadingProgress::where('user_id', $user->id)->pluck('book_id')->all();

        $query = Book::published()->with(['author', 'category']);

        if ($ownAuthorIds !== []) {
            $query->whereNotIn('author_id', $ownAuthorIds);
        }

        if ($readBookIds !== []) {
            $query->whereNotIn('id', $readBookIds);
        }

        return $query;
    }
}
