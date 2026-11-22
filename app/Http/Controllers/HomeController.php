<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookService;

class HomeController extends Controller
{
    use BuildsSeo;

    public function index(BookService $books)
    {
        $trending = Book::published()->with(['author', 'category'])
            ->orderByDesc('ratings_count')->limit(8)->get();
        $newest = Book::published()->with(['author', 'category'])
            ->orderByDesc('published_at')->orderByDesc('id')->limit(8)->get();
        $free = Book::published()->free()->with(['author', 'category'])
            ->orderByDesc('ratings_count')->limit(8)->get();
        $categories = Category::active()->orderBy('sort_order')->get();

        return view('home.index', [
            'trending' => $trending,
            'newest' => $newest,
            'freeBooks' => $free,
            'categories' => $categories,
            'seo' => $this->seo(__('books.home_title'), __('books.home_description')),
        ]);
    }
}
