<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Spec §2.1 — the 8 frozen books. Descriptions are verbatim from the
     * prototype's `about` text. NO fake reviews, NO fake ISBNs (NULL).
     * Prophet: price 96, list 120, sales 365.
     */
    public function run(): void
    {
        $books = [
            [
                'slug' => 'gitanjali', 'title' => 'Gitanjali', 'title_en' => 'Gitanjali',
                'author_slug' => 'rabindranath-tagore', 'category_slug' => 'poetry',
                'language' => 'en', 'price_bdt' => 0, 'list_price_bdt' => null, 'pages' => 104,
                'has_audiobook' => true, 'is_public_domain' => true,
                'rating_avg' => 4.9, 'ratings_count' => 2314,
                'cover_c1' => '#7a4a21', 'cover_c2' => '#96602c',
                'description' => "Tagore's beloved song offerings — the poems that carried Bengali literature to the world. A gentle first book for any new reader's shelf.",
                'description_en' => "Tagore's beloved song offerings — the poems that carried Bengali literature to the world. A gentle first book for any new reader's shelf.",
            ],
            [
                'slug' => 'padma-nadir-majhi', 'title' => 'পদ্মা নদীর মাঝি', 'title_en' => 'Padma Nadir Majhi',
                'author_slug' => 'manik-bandopadhyay', 'category_slug' => 'novel',
                'language' => 'bn', 'price_bdt' => 0, 'list_price_bdt' => null, 'pages' => 176,
                'has_audiobook' => true, 'is_public_domain' => true,
                'rating_avg' => 4.8, 'ratings_count' => 1892,
                'cover_c1' => '#14524a', 'cover_c2' => '#1f7a6a',
                'description' => 'কুবের মাঝির জীবন, পদ্মার ঢেউ আর হোসেন মিয়াঁর রহস্যময় দ্বীপ — বাংলা সাহিত্যের অন্যতম শ্রেষ্ঠ উপন্যাস, এখন সবার জন্য ফ্রি।',
                'description_en' => null,
            ],
            [
                'slug' => 'the-prophet', 'title' => 'The Prophet', 'title_en' => 'The Prophet',
                'author_slug' => 'kahlil-gibran', 'category_slug' => 'philosophy',
                'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'pages' => 96,
                'has_audiobook' => true, 'is_public_domain' => false,
                'rating_avg' => 4.9, 'ratings_count' => 3120, 'sales_count' => 365,
                'cover_c1' => '#33415c', 'cover_c2' => '#5c6b8a',
                'description' => 'Twenty-six prose poems on love, work, freedom and sorrow — short chapters that readers return to for a lifetime.',
                'description_en' => 'Twenty-six prose poems on love, work, freedom and sorrow — short chapters that readers return to for a lifetime.',
            ],
            [
                'slug' => 'devdas', 'title' => 'দেবদাস', 'title_en' => 'Devdas',
                'author_slug' => 'saratchandra-chattopadhyay', 'category_slug' => 'novel',
                'language' => 'bn', 'price_bdt' => 0, 'list_price_bdt' => null, 'pages' => 158,
                'has_audiobook' => false, 'is_public_domain' => true,
                'rating_avg' => 4.7, 'ratings_count' => 4210,
                'cover_c1' => '#6d2137', 'cover_c2' => '#9c3355',
                'description' => 'দেবদাস আর পার্বতীর অমর প্রেমের গল্প — শত বছর ধরে বাঙালি পাঠকের হৃদয়ে থাকা উপন্যাস।',
                'description_en' => null,
            ],
            [
                'slug' => 'pride-and-prejudice', 'title' => 'Pride and Prejudice', 'title_en' => 'Pride and Prejudice',
                'author_slug' => 'jane-austen', 'category_slug' => 'novel',
                'language' => 'en', 'price_bdt' => 150, 'list_price_bdt' => null, 'pages' => 432,
                'has_audiobook' => true, 'is_public_domain' => false,
                'rating_avg' => 4.8, 'ratings_count' => 2875,
                'cover_c1' => '#584b8f', 'cover_c2' => '#6f63ad',
                'description' => "Elizabeth Bennet and Mr Darcy in Austen's sparkling comedy of manners — the most loved English classic on the shelf.",
                'description_en' => "Elizabeth Bennet and Mr Darcy in Austen's sparkling comedy of manners — the most loved English classic on the shelf.",
            ],
            [
                'slug' => 'sesher-kobita', 'title' => 'শেষের কবিতা', 'title_en' => 'Sesher Kobita',
                'author_slug' => 'rabindranath-tagore', 'category_slug' => 'novel',
                'language' => 'bn', 'price_bdt' => 0, 'list_price_bdt' => null, 'pages' => 142,
                'has_audiobook' => true, 'is_public_domain' => true,
                'rating_avg' => 4.8, 'ratings_count' => 1654,
                'cover_c1' => '#8a5a16', 'cover_c2' => '#966e14',
                'description' => 'অমিত আর লাবণ্যর গল্প — প্রেম, কবিতা আর আধুনিক মনের টানাপোড়েন নিয়ে রবীন্দ্রনাথের অনন্য উপন্যাস।',
                'description_en' => null,
            ],
            [
                'slug' => 'prompt-engineering-basics', 'title' => 'Prompt Engineering Basics', 'title_en' => 'Prompt Engineering Basics',
                'author_slug' => 'learnvow-commissioned-series', 'category_slug' => 'skills',
                'language' => 'en', 'price_bdt' => 199, 'list_price_bdt' => null, 'pages' => 128,
                'has_audiobook' => false, 'is_public_domain' => false,
                'rating_avg' => 4.6, 'ratings_count' => 312,
                'cover_c1' => '#17573a', 'cover_c2' => '#2a7a52',
                'description' => 'From your own commissioned collection: how to write prompts that actually work — zero-shot, few-shot and chain-of-thought, with copy-paste templates.',
                'description_en' => 'From your own commissioned collection: how to write prompts that actually work — zero-shot, few-shot and chain-of-thought, with copy-paste templates.',
            ],
            [
                'slug' => 'meditations', 'title' => 'Meditations', 'title_en' => 'Meditations',
                'author_slug' => 'marcus-aurelius', 'category_slug' => 'philosophy',
                'language' => 'en', 'price_bdt' => 99, 'list_price_bdt' => null, 'pages' => 254,
                'has_audiobook' => true, 'is_public_domain' => false,
                'rating_avg' => 4.7, 'ratings_count' => 1980,
                'cover_c1' => '#3d3d3d', 'cover_c2' => '#6b6b6b',
                'description' => 'The private notebooks of a Roman emperor — practical Stoic wisdom on focus, patience and a calm mind.',
                'description_en' => 'The private notebooks of a Roman emperor — practical Stoic wisdom on focus, patience and a calm mind.',
            ],
        ];

        foreach ($books as $data) {
            $author = Author::where('slug', $data['author_slug'])->firstOrFail();
            $category = Category::where('slug', $data['category_slug'])->firstOrFail();

            Book::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'author_id' => $author->id,
                    'category_id' => $category->id,
                    'title' => $data['title'],
                    'title_en' => $data['title_en'],
                    'description' => $data['description'],
                    'description_en' => $data['description_en'],
                    'language' => $data['language'],
                    'price_bdt' => $data['price_bdt'],
                    'list_price_bdt' => $data['list_price_bdt'],
                    'isbn' => null,
                    'pages' => $data['pages'],
                    'has_audiobook' => $data['has_audiobook'],
                    'is_free' => $data['price_bdt'] === 0,
                    'is_public_domain' => $data['is_public_domain'],
                    'is_subscription_included' => true,
                    'status' => 'published',
                    'cover_c1' => $data['cover_c1'],
                    'cover_c2' => $data['cover_c2'],
                    'rights_territories' => ['BD'],
                    'rating_avg' => $data['rating_avg'],
                    'ratings_count' => $data['ratings_count'],
                    'sales_count' => $data['sales_count'] ?? 0,
                    'readers_count' => 0,
                    'published_at' => now(),
                ]
            );
        }
    }
}
