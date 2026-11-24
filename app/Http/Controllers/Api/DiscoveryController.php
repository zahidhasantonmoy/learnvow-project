<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Services\BookService;
use Illuminate\Http\Request;

/** For You (spec §4.3): Builder A's RecommendationService when it exists, else the popular-books fallback. */
class DiscoveryController extends ApiController
{
    public function forYou(Request $request, BookService $books)
    {
        $user = $request->user();
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        if (class_exists(\App\Services\RecommendationService::class)
            && method_exists(\App\Services\RecommendationService::class, 'forUser')) {
            $result = app(\App\Services\RecommendationService::class)->forUser($user, $limit);
            if (is_array($result) && $result !== []) {
                return $this->ok($result);
            }
        }

        // Fallback (spec §4.3): no reading history → popular published books.
        $popular = Book::published()->orderByDesc('sales_count')->orderByDesc('ratings_count')
            ->limit($limit)->get();

        $books->preloadOwnedBooks($user);

        return $this->ok($popular->map(fn (Book $book) => array_merge(
            $this->bookArray($book, $books->canRead($user, $book)),
            ['reason' => __('api.recommended_popular')]
        ))->values());
    }
}
