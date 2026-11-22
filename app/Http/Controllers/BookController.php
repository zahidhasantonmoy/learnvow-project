<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookService;
use Illuminate\Http\Request;

class BookController extends Controller
{
    use BuildsSeo;

    public function __construct(protected BookService $books) {}

    public function index(Request $request)
    {
        $filters = $request->only(['category_slug', 'price', 'language', 'sort']);
        $books = $this->books->list($filters);
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('books.index', [
            'books' => $books,
            'categories' => $categories,
            'filters' => $filters,
            'seo' => $this->seo(__('books.browse_title'), __('books.browse_description')),
        ]);
    }

    public function free(Request $request)
    {
        $books = $this->books->list(array_merge(
            $request->only(['category_slug', 'language', 'sort']),
            ['price' => 'free']
        ));
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('books.index', [
            'books' => $books,
            'categories' => $categories,
            'filters' => ['price' => 'free'],
            'isFreePage' => true,
            'seo' => $this->seo(__('books.free_title'), __('books.free_description')),
        ]);
    }

    public function show(string $locale, string $slug)
    {
        $book = $this->books->show($slug);
        $book->load(['approvedReviews.user']);
        $related = Book::published()->with(['author', 'category'])
            ->where('category_id', $book->category_id)
            ->where('id', '!=', $book->id)
            ->orderByDesc('ratings_count')->limit(4)->get();
        $user = auth()->user();
        $canRead = $this->books->canRead($user, $book);
        $inWishlist = $user
            ? $book->wishlists()->where('user_id', $user->id)->exists()
            : false;
        $myReview = $user
            ? $book->reviews()->where('user_id', $user->id)->first()
            : null;

        return view('books.show', [
            'book' => $book,
            'related' => $related,
            'canRead' => $canRead,
            'inWishlist' => $inWishlist,
            'myReview' => $myReview,
            'seo' => $this->seo(
                $book->title.' — '.$book->author->name,
                $book->language === 'bn' ? $book->description : ($book->description_en ?? $book->description),
                ['ogType' => 'book', 'jsonLd' => $this->books->bookJsonLd($book)]
            ),
        ]);
    }

    public function sample(string $locale, string $slug)
    {
        $book = $this->books->show($slug);

        return view('books.sample', [
            'book' => $book,
            'canRead' => $this->books->canRead(auth()->user(), $book),
            'seo' => $this->seo(
                __('books.sample_title', ['title' => $book->title]),
                $book->language === 'bn' ? $book->description : ($book->description_en ?? $book->description)
            ),
        ]);
    }
}
