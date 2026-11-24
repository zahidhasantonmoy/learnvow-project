<?php

namespace App\Http\Controllers\Api;

use App\Services\BookService;
use Illuminate\Http\Request;

/** My books — bought + Premium (spec §1.3). */
class LibraryController extends ApiController
{
    public function index(Request $request, BookService $books)
    {
        $user = $request->user();

        $rows = \App\Models\UserBook::where('user_id', $user->id)
            ->with(['book.author', 'book.category'])
            ->latest()->paginate(min(50, max(1, (int) $request->query('per_page', 12))));

        $items = $rows->getCollection()->map(function ($row) use ($books, $user) {
            $book = $row->book;
            if ($book === null) {
                return null;
            }
            $arr = $this->bookArray($book, $books->canRead($user, $book));
            $arr['source'] = $row->source;
            $arr['acquired_at'] = $row->created_at?->toIso8601String();

            return $arr;
        })->filter()->values();

        return response()->json([
            'data' => $items,
            'links' => ['first' => $rows->url(1), 'last' => $rows->url($rows->lastPage()), 'prev' => $rows->previousPageUrl(), 'next' => $rows->nextPageUrl()],
            'meta' => ['locale' => app()->getLocale(), 'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'has_premium' => $books->hasActivePremium($user)],
        ]);
    }
}
