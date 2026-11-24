<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Services\BookService;
use Illuminate\Http\Request;

/** Catalogue (spec §1.3): list / show / search. */
class BookController extends ApiController
{
    public function index(Request $request, BookService $books)
    {
        $page = $books->list([
            'category_id' => $request->query('category_id'),
            'category_slug' => $request->query('category_slug'),
            'price' => $request->query('price'),
            'language' => $request->query('language'),
            'sort' => $request->query('sort', 'newest'),
            'per_page' => min(50, max(1, (int) $request->query('per_page', 12))),
        ]);

        $user = $request->user();
        $books->preloadOwnedBooks($user);
        $items = $page->getCollection()->map(
            fn (Book $book) => $this->bookArray($book, $books->canRead($user, $book))
        )->values();

        return response()->json([
            'data' => $items,
            'links' => [
                'first' => $page->url(1), 'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl(),
            ],
            'meta' => [
                'locale' => app()->getLocale(),
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, Book $book, BookService $books)
    {
        if ($book->status !== 'published' || $book->is_paused || $book->hidden_at !== null) {
            abort(404);
        }

        return $this->ok($this->bookArray($book->load(['author', 'category', 'tags']), $books->canRead($request->user(), $book)));
    }

    public function search(Request $request, BookService $books)
    {
        $query = (string) $request->query('q', '');
        $found = $books->search($query);
        $user = $request->user();
        $books->preloadOwnedBooks($user);

        return $this->ok($found->map(
            fn (Book $book) => $this->bookArray($book, $books->canRead($user, $book))
        )->values());
    }
}
