<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Category;
use App\Services\BookService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use BuildsSeo;

    public function show(Request $request, string $locale, string $slug, BookService $books)
    {
        $category = Category::active()->where('slug', $slug)->firstOrFail();
        $list = $books->list(array_merge(
            $request->only(['price', 'language', 'sort']),
            ['category_slug' => $slug]
        ));

        $itemList = $list->getCollection()->values()->map(fn ($book, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'url' => route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]),
            'name' => $book->title,
        ])->all();

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'ItemList',
                    'name' => $category->displayName(),
                    'itemListElement' => $itemList,
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'LearnVow',
                            'item' => url('/'.app()->getLocale())],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $category->displayName()],
                    ],
                ],
            ],
        ];

        return view('books.index', [
            'books' => $list,
            'categories' => Category::active()->orderBy('sort_order')->get(),
            'filters' => ['category_slug' => $slug],
            'category' => $category,
            'seo' => $this->seo(
                $category->displayName().' — LearnVow',
                $category->displayDescription(),
                ['jsonLd' => $jsonLd]
            ),
        ]);
    }
}
