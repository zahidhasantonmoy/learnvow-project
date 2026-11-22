<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BlogController;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];
        foreach (['bn', 'en'] as $locale) {
            $urls[] = ['loc' => url("/{$locale}"), 'lastmod' => now()->toDateString()];
        }
        foreach (Book::published()->get() as $book) {
            foreach (['bn', 'en'] as $locale) {
                $urls[] = [
                    'loc' => route('books.show', ['locale' => $locale, 'slug' => $book->slug]),
                    'lastmod' => $book->updated_at->toDateString(),
                ];
            }
        }
        foreach (Author::approved()->get() as $author) {
            foreach (['bn', 'en'] as $locale) {
                $urls[] = [
                    'loc' => route('authors.show', ['locale' => $locale, 'slug' => $author->slug]),
                    'lastmod' => $author->updated_at->toDateString(),
                ];
            }
        }
        foreach (Category::active()->get() as $category) {
            foreach (['bn', 'en'] as $locale) {
                $urls[] = [
                    'loc' => route('categories.show', ['locale' => $locale, 'slug' => $category->slug]),
                    'lastmod' => $category->updated_at->toDateString(),
                ];
            }
        }
        foreach (BlogController::articles() as $article) {
            foreach (['bn', 'en'] as $locale) {
                $urls[] = [
                    'loc' => route('blog.show', ['locale' => $locale, 'slug' => $article['slug']]),
                    'lastmod' => $article['published_at'],
                ];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc><lastmod>'.$u['lastmod'].'</lastmod></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
